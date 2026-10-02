<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Deprecated;
use Illuminate\Foundation\Application;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\DeprecationReason;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ParameterClassifier;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeCollector;
use Rebing\GraphQL\GraphQL;
use Rebing\GraphQL\Support\Mutation as RebingMutation;
use Rebing\GraphQL\Support\Query as RebingQuery;
use Rebing\GraphQL\Support\Type as RebingType;
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

            if ($action->type === null && $action->of === null) {
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

            $typeBuilder = $this->resolveTypeBuilder($class, $method);

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
                TypeRef::fromAction($action),
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
     * Cross-item checks on the discovered types.
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
        $returnType = $method->getReturnType();

        if ($returnType?->getName() === 'void') {
            return ['void', true];
        }

        if ($returnType !== null && $returnType->isScalar()) {
            return [$returnType->getName(), $returnType->isNullable()];
        }

        throw new RuntimeException(sprintf(
            'Method %s::%s has no scalar return type. Specify type: in #[%s], or use a scalar or void return type hint.',
            $class->getName(),
            $method->getName(),
            class_basename($action::class),
        ));
    }
}
