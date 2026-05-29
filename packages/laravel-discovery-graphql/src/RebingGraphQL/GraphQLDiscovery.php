<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Deprecated;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Foundation\Application;
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
use Tempest\Reflection\ParameterReflector;
use Tempest\Reflection\TypeReflector;

final class GraphQLDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!class_exists(GraphQL::class) || ! $class->isInstantiable()) {
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

            if ($action->type === null) {
                [$action->type, $action->nullable] = $this->discoverActionReturnType($action, $class, $method);
            }

            $decorators = [
                ...$method->getAttributes(ActionDecorator::class),
                ...$classDecorators,
            ];

            foreach ($decorators as $decorator) {
                $decorator->decorate($action);
            }

            $argProviders = [
                ...$method->getAttributes(ActionArgProvider::class),
                ...$class->getAttributes(ActionArgProvider::class),
            ];

            $valueObjectClasses = $this->collectValueObjectClasses($argProviders, $class, $method);

            $args = [];
            $injections = [];
            $containerInjections = [];
            $argCompositions = [];

            foreach ($method->getParameters() as $param) {
                // Parameters carrying a Laravel ContextualAttribute (e.g. #[CurrentUser], #[Config])
                // are left untouched at discovery so that $container->call() can resolve them via
                // the attribute's resolve() hook. Pre-filling $mappedArgs would override the attribute.
                if ($param->getAttribute(ContextualAttribute::class) !== null) {
                    continue;
                }

                /** @var Arg|null $argAttr */
                $argAttr = $param->getAttribute(Arg::class);

                $kind = $this->detectInjectionKind($param);

                if ($kind !== null) {
                    $injections[$param->getName()] = $kind;
                    continue;
                }

                $type = $param->getType();

                if (!$argAttr && !$type->isScalar()) {
                    if ($this->shouldComposeFromArgs($type, $valueObjectClasses)) {
                        $argCompositions[$param->getName()] = $param->getType()->getName();
                        continue;
                    }

                    if ($this->shouldContainerResolve($type)) {
                        $containerInjections[$param->getName()] = $param->getType()->getName();
                        continue;
                    }
                }

                $args[] = $this->discoverActionParameter($argAttr, $param, $class, $method);
            }

            $middleware = [
                ...$classMiddleware,
                ...collect($method->getAttributes(Middleware::class))
                    ->flatMap(fn(Middleware $m) => $m->middleware)
                    ->all(),
            ];

            $authorizations = [
                ...$classAuthorizations,
                ...$method->getAttributes(Authorize::class),
            ];

            $typeBuilder = $this->resolveTypeBuilder($class, $method);

            $this->discoveryItems->add($location, new DiscoveredAction(
                $action,
                $class->getName(),
                $method->getName(),
                $args,
                $injections,
                $middleware,
                $this->resolveDeprecationReason($method->getAttribute(Deprecated::class)),
                $authorizations,
                $typeBuilder,
                $containerInjections,
                $argProviders,
                $argCompositions,
            )->withBindName());
        }
    }

    public function apply(): void
    {
        if ($this->app->configurationIsCached()) {
            foreach ($this->discoveryItems as $item) {
                if ($item instanceof DiscoveredAction) {
                    $this->app->singleton($item->bindName, $item->createType(...));
                }
            }

            return;
        }

        $config = $this->app->make('config');
        $defaultSchema = $config->get('graphql.default_schema', 'default');

        $schemas = [];

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredAction) {
                $this->app->singleton($item->bindName, $item->createType(...));
                $schemas[$item->action->schema ?? $defaultSchema][$item->fieldType][$item->action->name] = $item->bindName;
            } elseif ($item instanceof DiscoveredField) {
                $schemas[$item->schema ?? $defaultSchema][$item->fieldType][$item->getName()] = $item->class;
            }
        }

        $config->set('graphql.schemas', array_merge_recursive(
            $config->get('graphql.schemas', []),
            $schemas,
        ));
    }

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
     * @param  list<ActionArgProvider>  $argProviders
     * @return array<class-string<ComposedFromArgs>, true>
     */
    private function collectValueObjectClasses(array $argProviders, ClassReflector $class, MethodReflector $method): array
    {
        $seen = [];

        foreach ($argProviders as $provider) {
            foreach (array_keys($provider->provideArgs()) as $name) {
                if (isset($seen[$name])) {
                    throw new RuntimeException(sprintf(
                        'Method %s::%s has multiple ActionArgProvider attributes declaring the same arg "%s".',
                        $class->getName(),
                        $method->getName(),
                        $name,
                    ));
                }
                $seen[$name] = true;
            }
        }

        $valueObjectClasses = [];

        foreach ($argProviders as $provider) {
            foreach ($provider->provideValueObjects() as $valueObject) {
                $valueObjectClasses[$valueObject] = true;
            }
        }

        return $valueObjectClasses;
    }

    /**
     * @param array<class-string<ComposedFromArgs>, true> $valueObjectClasses
     */
    private function shouldComposeFromArgs(TypeReflector $type, array $valueObjectClasses): bool
    {
        $typeName = $type->getName();

        return isset($valueObjectClasses[$typeName])
            && is_a($typeName, ComposedFromArgs::class, true);
    }

    private function shouldContainerResolve(TypeReflector $type): bool
    {
        $typeName = $type->getName();

        return class_exists($typeName) || interface_exists($typeName);
    }

    /**
     * @return 'root'|'context'|'info'|null
     */
    private function detectInjectionKind(ParameterReflector $param): ?string
    {
        if ($param->getAttribute(Root::class) !== null) {
            return 'root';
        }

        if ($param->getAttribute(Context::class) !== null) {
            return 'context';
        }

        $type = $param->getType();

        if (! $type->isScalar() && is_a($type->getName(), ResolveInfo::class, true)) {
            return 'info';
        }

        return null;
    }

    private function resolveDeprecationReason(?Deprecated $deprecated): ?string
    {
        if ($deprecated === null) {
            return null;
        }

        $message = $deprecated->message;
        $since = $deprecated->since;

        return match (true) {
            $message !== null && $since !== null => "$message (since $since)",
            $message !== null => $message,
            $since !== null => "Deprecated since $since",
            default => 'Deprecated',
        };
    }

    /**
     * @return array{0: string, 1: bool} [type, nullable]
     */
    private function discoverActionReturnType(Action $action, ClassReflector $class, MethodReflector $method): array
    {
        $returnType = $method->getReturnType();
        $phpType = $returnType?->getName();

        if ($phpType === 'void') {
            return ['void', true];
        }

        if ($returnType !== null && $returnType->isScalar()) {
            return [$phpType, $returnType->isNullable()];
        }

        throw new RuntimeException(sprintf(
            'Method %s::%s has no scalar return type. Specify type: in #[%s], or use a scalar or void return type hint.',
            $class->getName(),
            $method->getName(),
            class_basename($action::class),
        ));
    }

    private function discoverActionParameter(?Arg $argAttr, ParameterReflector $param, ClassReflector $class, MethodReflector $method): DiscoveredArg
    {
        $typeReflector = $param->getType();

        if ($argAttr?->type !== null) {
            $typeName = $argAttr->type;
        } elseif ($typeReflector->isScalar()) {
            $typeName = $typeReflector->getName();
        } else {
            throw new RuntimeException(sprintf(
                'Parameter $%s in %s::%s is not a scalar type. Use #[Arg(type: \'GraphQLTypeName\')] to specify the GraphQL type.',
                $param->getName(),
                $class->getName(),
                $method->getName(),
            ));
        }

        $hasRules = !empty($argAttr?->rules);
        $hasDefault = $param->hasDefaultValue();

        return new DiscoveredArg(
            name: $argAttr?->name ?? $param->getName(),
            paramName: $param->getName(),
            type: $typeName,
            nullable: $typeReflector->isNullable() || $hasDefault,
            description: $argAttr?->description,
            hasRules: $hasRules,
            hasDefault: $hasDefault,
            defaultValue: $hasDefault ? $param->getDefaultValue() : null,
            deprecationReason: $argAttr?->deprecationReason,
        );
    }
}
