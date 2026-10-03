<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Deprecated;
use Illuminate\Foundation\Application;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\DeprecationReason;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ParameterClassifier;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeCollector;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeInferrer;
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
    ) {}

    /**
     * @param ClassReflector<object> $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!class_exists(GraphQL::class)) {
            return;
        }

        $type = $class->getAttribute(Type::class);

        if ($type !== null) {
            $this->addType($location, $this->types->collect($class, $type));
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

            if ($typeBuilder === null && $action->type === null && $action->of === null) {
                [$action->type, $action->nullable] = $this->discoverActionReturnType($action, $class, $method);
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
                $action->type === null && $action->of === null ? null : TypeRef::fromAction($action),
            )->withBindName());
        }
    }

    public function apply(): void
    {
        $types = $this->bindSingletons();

        $this->registerTypes($types);
        $this->validate($types);

        if (! $this->app->configurationIsCached()) {
            $this->writeConfig();
        }
    }

    /**
     * @return list<DiscoveredType>
     */
    private function bindSingletons(): array
    {
        $types = [];

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredAction && $item->bindName !== null) {
                $this->app->singleton($item->bindName, $item->createType(...));
            } elseif ($item instanceof DiscoveredType && $item->bindName !== null) {
                $this->app->singleton($item->bindName, $item->createType(...));
                $types[] = $item;
            }
        }

        return $types;
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
                    'GraphQL type name [%s] is used by both %s (#[Type]) and the Rebing type %s. Rename one with #[Type(name: ...)].',
                    $type->name,
                    $type->class,
                    $taken,
                ));
            }
        }

        $this->assertClassReferencesRegistered($this->app->make(TypeRegistry::class), $types);
    }

    /**
     * Every class-string an action return or a type field points at must be a registered output type.
     *
     * @param  list<DiscoveredType>  $types
     */
    private function assertClassReferencesRegistered(TypeRegistry $registry, array $types): void
    {
        foreach ($this->classReferences($types) as [$class, $referrer, $attribute]) {
            if ($registry->has($class, Position::Output)) {
                continue;
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
     * @return iterable<array{0: class-string, 1: string, 2: string}> [class, referrer, attribute]
     */
    private function classReferences(array $types): iterable
    {
        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredAction && $item->returnType?->class !== null) {
                yield [
                    $item->returnType->class,
                    "Method {$item->class}::{$item->method}",
                    class_basename($item->action::class),
                ];
            }
        }

        foreach ($types as $type) {
            foreach ($type->fields as $field) {
                if ($field->type->class !== null) {
                    yield [$field->type->class, "Field {$type->name}.{$field->name}", 'Field'];
                }
            }
        }
    }

    private function addType(DiscoveryLocation $location, DiscoveredType $type): void
    {
        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredType && $item->name === $type->name && $item->class !== $type->class) {
                throw new LogicException(sprintf(
                    'GraphQL type name [%s] is used by both %s and %s. Rename one with #[Type(name: ...)].',
                    $type->name,
                    $item->class,
                    $type->class,
                ));
            }
        }

        $this->discoveryItems->add($location, $type->withBindName());
    }

    private function writeConfig(): void
    {
        $config = $this->app->make('config');
        $defaultSchema = $config->string('graphql.default_schema', 'default');

        $schemas = [];
        $types = [];

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredAction && $item->bindName !== null) {
                $fieldName = $item->action->name ?? $item->method;
                $schemas[$item->action->schema ?? $defaultSchema][$item->fieldType][$fieldName] = $item->bindName;
            } elseif ($item instanceof DiscoveredType && $item->bindName !== null) {
                $types[$item->name] = $item->bindName;
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
     * @return array{0: string, 1: bool} [type, nullable]
     */
    private function discoverActionReturnType(Action $action, ClassReflector $class, MethodReflector $method): array
    {
        $returnType = $method->getReflection()->getReturnType();

        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === 'void') {
            return ['void', true];
        }

        try {
            $ref = $this->inferrer->output(
                $returnType,
                $class->getName(),
                sprintf('Method %s::%s', $class->getName(), $method->getName()),
                class_basename($action::class),
                nullable: $action->nullable,
            );
        } catch (LogicException $e) {
            throw new RuntimeException(
                $e->getMessage() . ' A scalar, void or #[Type] class return type is inferred.',
                previous: $e,
            );
        }

        return [(string) ($ref->class ?? $ref->scalar), $ref->nullable];
    }
}
