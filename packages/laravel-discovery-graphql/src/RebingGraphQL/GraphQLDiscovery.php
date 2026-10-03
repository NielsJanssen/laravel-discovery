<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Deprecated;
use Illuminate\Foundation\Application;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\DeprecationReason;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\EnumCollector;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ParameterClassifier;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeCollector;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeInferrer;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use Rebing\GraphQL\GraphQL;
use Rebing\GraphQL\Support\Mutation as RebingMutation;
use Rebing\GraphQL\Support\Query as RebingQuery;
use Rebing\GraphQL\Support\Type as RebingType;
use ReflectionNamedType;
use RuntimeException;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;

final class GraphQLDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
        private readonly ParameterClassifier $parameters,
        private readonly TypeCollector $types,
        private readonly TypeInferrer $inferrer,
        private readonly EnumCollector $enums,
    ) {}

    /**
     * @param ClassReflector<object> $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!class_exists(GraphQL::class)) {
            return;
        }

        if ($class->hasAttribute(Enum::class)) {
            $this->addType($location, $this->enums->collect($class->getName()));
        }

        $type = $class->getAttribute(Type::class);

        if ($type !== null) {
            $collected = $this->types->collect($class, $type);

            $this->addType($location, $collected);
            $this->addReferencedEnums($location, $this->fieldTypeReferences($collected));
        }

        if (! $class->isInstantiable()) {
            return;
        }

        if ($class->is(RebingType::class)) {
            $this->discoveryItems->add($location, new DiscoveredField('types', $class->getName()));

            return;
        }

        if ($class->is(RebingQuery::class) && ! $class->is(QueryField::class)) {
            $this->discoveryItems->add($location, new DiscoveredField('query', $class->getName()));

            return;
        }

        if ($class->is(RebingMutation::class) && ! $class->is(MutationField::class)) {
            $this->discoveryItems->add($location, new DiscoveredField('mutation', $class->getName()));

            return;
        }

        $classDecorators = $class->getAttributes(ActionDecorator::class);
        $classMiddleware = collect($class->getAttributes(Middleware::class))
            ->flatMap(fn(Middleware $m) => $m->middleware)
            ->all();
        $classAuthorizations = $class->getAttributes(Authorize::class);

        foreach ($class->getPublicMethods() as $method) {
            $action = $method->getAttribute(Query::class) ?? $method->getAttribute(Mutation::class);

            if (! $action) {
                continue;
            }

            if ($action->type !== null && $action->of !== null) {
                throw new LogicException(sprintf(
                    'Method %s::%s sets both type: and of: on #[%s]. Use of: for a list of that type, or type: for a single value.',
                    $class->getName(),
                    $method->getName(),
                    class_basename($action::class),
                ));
            }

            $typeBuilder = $this->resolveTypeBuilder($class, $method);

            $inferred = null;

            if ($typeBuilder === null && $action->type === null && $action->of === null) {
                $inferred = $this->discoverActionReturnType($action, $class, $method);
                $action->type = $inferred->target();
                $action->nullable = $inferred->nullable;
                $action->list = $action->list || $inferred->list;
                $action->nullableItems = $action->nullableItems || $inferred->nullableItems;
            } elseif ($method->getReturnType()?->isNullable() === true) {
                // An explicit type: says which type, not whether the field may be null, so a `?Type`
                // return still widens it. Inference only ever turns nullability on.
                $action->nullable = true;
            }

            $decorators = [
                ...$method->getAttributes(ActionDecorator::class),
                ...$classDecorators,
            ];

            foreach ($decorators as $decorator) {
                $decorator->decorate($action);
            }

            $argProviders = array_values([
                ...$method->getAttributes(ActionArgProvider::class),
                ...$class->getAttributes(ActionArgProvider::class),
            ]);

            $parameters = $this->parameters->classify($class, $method, $argProviders);

            $middleware = [
                ...$classMiddleware,
                ...collect($method->getAttributes(Middleware::class))
                    ->flatMap(fn(Middleware $m) => $m->middleware)
                    ->all(),
            ];

            $authorizations = array_values([
                ...$classAuthorizations,
                ...$method->getAttributes(Authorize::class),
            ]);

            if (array_any($authorizations, static fn(Authorize $authorize): bool => $authorize->onDenied !== null)) {
                throw new LogicException(sprintf(
                    'Method %s::%s has #[Authorize(onDenied:)], which only applies to a field of a #[Type]. A denied #[%s] always reports an error; remove onDenied:.',
                    $class->getName(),
                    $method->getName(),
                    class_basename($action::class),
                ));
            }

            foreach ($authorizations as $authorize) {
                if ($authorize->ability !== null && $authorize->gate !== null) {
                    throw new LogicException(sprintf(
                        "Method %s::%s has #[Authorize] with both an ability and gate:. A gate decides on its own: remove the ability, or move it to the model-bound parameter as #[Authorize('%s')].",
                        $class->getName(),
                        $method->getName(),
                        $authorize->ability,
                    ));
                }

                if ($authorize->gate !== null && ! is_a($authorize->gate, AuthorizationGate::class, true)) {
                    throw new LogicException(sprintf(
                        'Method %s::%s has #[Authorize(gate: %s)], which does not implement %s.',
                        $class->getName(),
                        $method->getName(),
                        $authorize->gate,
                        AuthorizationGate::class,
                    ));
                }

                if ($authorize->ability !== null) {
                    throw new LogicException(sprintf(
                        "Method %s::%s has #[Authorize('%s')] on the class or method, where there is no record to check the ability against. Put it on the model-bound parameter, or use #[Authorize(gate: ...)].",
                        $class->getName(),
                        $method->getName(),
                        $authorize->ability,
                    ));
                }
            }

            $returnType = match (true) {
                $inferred !== null => $inferred->wrapped($action->list, $action->nullable, $action->nullableItems),
                $action->type === null && $action->of === null => null,
                default => TypeRef::fromAction($action),
            };

            $this->addReferencedEnums($location, [
                $returnType?->class,
                ...array_map(static fn(DiscoveredArg $arg): ?string => $arg->ref()->class, $parameters->args),
            ]);

            $this->discoveryItems->add($location, new DiscoveredAction(
                $action,
                $class->getName(),
                $method->getName(),
                $parameters->args,
                $parameters->injections,
                $middleware,
                DeprecationReason::from($method->getAttribute(Deprecated::class)),
                $authorizations,
                $typeBuilder,
                $parameters->containerInjections,
                $argProviders,
                $parameters->argCompositions,
                $parameters->modelBindings,
                $returnType,
            )->withBindName());
        }
    }

    public function apply(): void
    {
        $types = $this->typesToRegister();

        $this->bindSingletons($types);
        $this->registerTypes($types);
        $this->validate($types);

        if (! $this->app->configurationIsCached()) {
            $this->writeConfig($types);
        }
    }

    /**
     * The discovered types, without implicit enums that an #[Enum], an earlier item or a hand registration covers.
     *
     * @return list<DiscoveredType>
     */
    private function typesToRegister(): array
    {
        $explicit = [];

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredType && ! $item->implicit) {
                $explicit[$item->class] = true;
            }
        }

        $registry = $this->app->make(TypeRegistry::class);
        $seen = [];
        $types = [];

        foreach ($this->discoveryItems as $item) {
            if (! $item instanceof DiscoveredType || $item->bindName === null) {
                continue;
            }

            if ($item->implicit && (isset($explicit[$item->class]) || isset($seen[$item->class]) || $registry->has($item->class))) {
                continue;
            }

            $seen[$item->class] = true;
            $types[] = $item;
        }

        return $types;
    }

    /**
     * @param  list<DiscoveredType>  $types
     */
    private function bindSingletons(array $types): void
    {
        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredAction && $item->bindName !== null) {
                $this->app->singleton($item->bindName, $item->createType(...));
            }
        }

        foreach ($types as $type) {
            $this->app->singleton((string) $type->bindName, $type->createType(...));
        }
    }

    /**
     * Register an implicit enum for every enum class among the references, unless one is already known.
     *
     * @param  iterable<string|null>  $references
     */
    private function addReferencedEnums(DiscoveryLocation $location, iterable $references): void
    {
        $registry = $this->app->make(TypeRegistry::class);

        foreach ($references as $class) {
            if ($class === null || ! enum_exists($class) || $registry->has($class) || $this->hasTypeFor($class)) {
                continue;
            }

            $this->addType($location, $this->enums->collect($class, implicit: true));
        }
    }

    private function hasTypeFor(string $class): bool
    {
        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredType && $item->class === $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<string|null>
     */
    private function fieldTypeReferences(DiscoveredType $type): iterable
    {
        foreach ($type->fields as $field) {
            yield $field->type->class;

            foreach ($field->parameters->args as $arg) {
                yield $arg->ref()->class;
            }
        }
    }

    /**
     * @param  list<DiscoveredType>  $types
     */
    private function registerTypes(array $types): void
    {
        $registry = $this->app->make(TypeRegistry::class);

        foreach ($types as $type) {
            $registry->register($type->class, $type->name, $type->kind);
        }
    }

    /**
     * Cross-item checks on the discovered types and the class references of actions.
     *
     * @param  list<DiscoveredType>  $types
     */
    private function validate(array $types): void
    {
        $handWritten = [];

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredField && $item->fieldType === 'types' && ($name = $item->getName()) !== null) {
                $handWritten[$name] = $item->class;
            }
        }

        foreach ($types as $type) {
            $taken = $handWritten[$type->name] ?? null;

            if ($taken !== null) {
                throw new LogicException(sprintf(
                    'GraphQL type name [%s] is used by both %s (#[%s]) and the Rebing type %s. %s',
                    $type->name,
                    $type->class,
                    $this->attributeOf($type),
                    $taken,
                    $this->renameHint($type),
                ));
            }
        }

        $this->assertClassReferencesRegistered($this->app->make(TypeRegistry::class), $types);
    }

    /**
     * Every class-string an action return or a type field points at must be a registered output type, and every
     * class-string an argument points at a registered input type.
     *
     * @param  list<DiscoveredType>  $types
     */
    private function assertClassReferencesRegistered(TypeRegistry $registry, array $types): void
    {
        foreach ($this->classReferences($types) as [$class, $referrer, $attribute, $position]) {
            if ($registry->has($class, $position)) {
                continue;
            }

            if ($position === Position::Input) {
                throw new LogicException(sprintf(
                    '%s references %s, which is not a registered GraphQL input type%s. Use a scalar or an enum, or name a registered GraphQL input type with #[Arg(type: ...)].',
                    $referrer,
                    $class,
                    $registry->has($class) ? ' (it is registered as ' . $registry->kindOf($class, Position::Output)->value . ' type [' . $registry->nameOf($class, Position::Output) . '])' : '',
                ));
            }

            if (is_a($class, RebingType::class, true)) {
                throw new LogicException(sprintf(
                    '%s references the Rebing type class %s. Reference a hand-written Rebing type by its GraphQL name with type: (or of: for a list) on #[%s].',
                    $referrer,
                    $class,
                    $attribute,
                ));
            }

            throw new LogicException(sprintf(
                '%s references %s, which is not a registered GraphQL output type. Add #[Type] to %s, or name a registered GraphQL type with type: (or of: for a list) on #[%s].',
                $referrer,
                $class,
                class_basename($class),
                $attribute,
            ));
        }
    }

    /**
     * @param  list<DiscoveredType>  $types
     * @return iterable<array{0: class-string, 1: string, 2: string, 3: Position}> [class, referrer, attribute, position]
     */
    private function classReferences(array $types): iterable
    {
        foreach ($this->discoveryItems as $item) {
            if (! $item instanceof DiscoveredAction) {
                continue;
            }

            $method = "Method {$item->class}::{$item->method}";

            if ($item->returnType?->class !== null) {
                yield [$item->returnType->class, $method, class_basename($item->action::class), Position::Output];
            }

            yield from $this->argClassReferences($item->args, $method);
        }

        foreach ($types as $type) {
            foreach ($type->fields as $field) {
                $member = "Field {$type->name}.{$field->name}";

                if ($field->type->class !== null) {
                    yield [$field->type->class, $member, 'Field', Position::Output];
                }

                yield from $this->argClassReferences($field->parameters->args, $member);
            }
        }
    }

    /**
     * @param  array<DiscoveredArg>  $args
     * @return iterable<array{0: class-string, 1: string, 2: string, 3: Position}>
     */
    private function argClassReferences(array $args, string $member): iterable
    {
        foreach ($args as $arg) {
            $class = $arg->ref()->class;

            if ($class !== null) {
                yield [$class, "Argument {$arg->name} of " . lcfirst($member), 'Arg', Position::Input];
            }
        }
    }

    private function addType(DiscoveryLocation $location, DiscoveredType $type): void
    {
        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredType && $item->name === $type->name && $item->class !== $type->class) {
                throw new LogicException(sprintf(
                    'GraphQL type name [%s] is used by both %s and %s. %s',
                    $type->name,
                    $item->class,
                    $type->class,
                    $this->renameHint($type),
                ));
            }
        }

        $this->discoveryItems->add($location, $type->withBindName());
    }

    private function attributeOf(DiscoveredType $type): string
    {
        return $type->kind === TypeKind::Enum ? 'Enum' : 'Type';
    }

    private function renameHint(DiscoveredType $type): string
    {
        if ($type->kind !== TypeKind::Enum) {
            return 'Rename one with #[Type(name: ...)].';
        }

        return sprintf(
            'Rename one with #[Enum(name: ...)], or register the enum by hand with %s::register() in a service provider that boots before %s.',
            TypeRegistry::class,
            'NielsJanssen\\Laravel\\Discovery\\DiscoveryServiceProvider',
        );
    }

    /**
     * @param  list<DiscoveredType>  $registered
     */
    private function writeConfig(array $registered): void
    {
        $config = $this->app->make('config');
        $defaultSchema = $config->string('graphql.default_schema', 'default');

        $schemas = [];
        $types = [];
        $kept = array_flip(array_map(spl_object_id(...), $registered));

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredAction && $item->bindName !== null) {
                $fieldName = $item->action->name ?? $item->method;
                $schemas[$item->action->schema ?? $defaultSchema][$item->fieldType][$fieldName] = $item->bindName;
            } elseif ($item instanceof DiscoveredType) {
                if (isset($kept[spl_object_id($item)])) {
                    $types[$item->name] = (string) $item->bindName;
                }
            } elseif ($item instanceof DiscoveredField && ($fieldName = $item->getName()) !== null) {
                if ($item->fieldType === 'types') {
                    $types[$fieldName] = $item->class;
                } else {
                    $schemas[$item->schema][$item->fieldType][$fieldName] = $item->class;
                }
            }
        }

        $config->set('graphql.schemas', array_merge_recursive(
            $config->array('graphql.schemas', []),
            $schemas,
        ));

        $config->set('graphql.types', [
            ...$config->array('graphql.types', []),
            ...$types,
        ]);
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function resolveTypeBuilder(ClassReflector $class, MethodReflector $method): ?ActionTypeBuilder
    {
        $methodBuilders = $method->getAttributes(ActionTypeBuilder::class);

        if (count($methodBuilders) > 1) {
            throw new RuntimeException(sprintf(
                'Method %s::%s has multiple ActionTypeBuilder attributes (%s). At most one is allowed per method.',
                $class->getName(),
                $method->getName(),
                implode(', ', array_map(static fn($b) => $b::class, $methodBuilders)),
            ));
        }

        if (! empty($methodBuilders)) {
            return $methodBuilders[0];
        }

        $classBuilders = $class->getAttributes(ActionTypeBuilder::class);

        if (count($classBuilders) > 1) {
            throw new RuntimeException(sprintf(
                'Class %s has multiple ActionTypeBuilder attributes (%s). At most one is allowed per class.',
                $class->getName(),
                implode(', ', array_map(static fn($b) => $b::class, $classBuilders)),
            ));
        }

        return $classBuilders[0] ?? null;
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function discoverActionReturnType(Action $action, ClassReflector $class, MethodReflector $method): TypeRef
    {
        $returnType = $method->getReflection()->getReturnType();

        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void') {
            return TypeRef::scalar('void', nullable: true);
        }

        try {
            return $this->inferrer->output(
                $returnType,
                $class->getName(),
                sprintf('Method %s::%s', $class->getName(), $method->getName()),
                class_basename($action::class),
                nullable: $action->nullable,
                member: new Member($method->getName(), $method->getReflection()->getDeclaringClass()->getName(), Position::Output, MemberKind::MethodReturn),
            );
        } catch (LogicException $e) {
            throw new RuntimeException(
                $e->getMessage() . ' A scalar, void, enum or #[Type] class return type is inferred.',
                previous: $e,
            );
        }
    }
}
