<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Deprecated;
use Illuminate\Foundation\Application;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\DeprecationReason;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\EnumCollector;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\Extensions;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\InputCollector;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ParameterClassifier;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\Replacements;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\ReturnTypeResolver;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\SchemaValidator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeCollector;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeReference;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery\TypeUsage;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use Rebing\GraphQL\GraphQL;
use Rebing\GraphQL\Support\Middleware as RebingMiddleware;
use Rebing\GraphQL\Support\Mutation as RebingMutation;
use Rebing\GraphQL\Support\Query as RebingQuery;
use Rebing\GraphQL\Support\Type as RebingType;
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
        private readonly EnumCollector $enums,
        private readonly InputCollector $inputs,
        private readonly Naming $names,
        private readonly SchemaValidator $validator,
        private readonly TypeUsage $usage,
        private readonly ReturnTypeResolver $returns,
    ) {}

    /**
     * @param ClassReflector<object> $class
     */
    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        if (!class_exists(GraphQL::class)) {
            return;
        }

        $this->discoverTypes($location, $class);

        if (! $class->isInstantiable()) {
            return;
        }

        if ($class->is(TypeProvider::class)) {
            $this->discoveryItems->add($location, new DiscoveredTypeProvider($class->getName()));
        }

        $fieldType = $this->rebingFieldType($class);

        if ($fieldType !== null) {
            $this->discoveryItems->add($location, new DiscoveredField($fieldType, $class->getName()));

            return;
        }

        foreach ($class->getPublicMethods() as $method) {
            $action = $method->getAttribute(Query::class) ?? $method->getAttribute(Mutation::class);

            if ($action) {
                $this->discoverAction($location, $class, $method, $action);
            }
        }
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function discoverTypes(DiscoveryLocation $location, ClassReflector $class): void
    {
        if ($class->hasAttribute(Enum::class)) {
            $this->addType($location, $this->enums->collect($class->getName()));
        }

        $type = $class->getAttribute(Type::class);

        if ($type !== null) {
            $collected = $this->types->collect($class, $type);

            $this->addType($location, $collected);
            $this->addImplicitEnums($location, $this->usage->ofType($collected));
        }

        $extend = $class->getAttribute(TypeExtension::class);

        if ($extend !== null) {
            $contributed = $this->types->contributed($class, $extend);

            $this->discoveryItems->add($location, $contributed);
            $this->addImplicitEnums($location, $this->usage->ofExtension($contributed));
        }

        $input = $class->getAttribute(Input::class);

        if ($input !== null) {
            $collected = $this->inputs->collect($class, $input);

            $this->addType($location, $collected);
            $this->addImplicitEnums($location, $this->usage->ofType($collected));
        }
    }

    /**
     * Register an implicit enum for every enum class among the references, unless one is already known.
     *
     * @param  iterable<TypeReference>  $references
     */
    private function addImplicitEnums(DiscoveryLocation $location, iterable $references): void
    {
        foreach ($this->usage->missingEnums($this->discoveryItems, $references) as $enum) {
            $this->addType($location, $this->enums->collect($enum, implicit: true));
        }
    }

    private function addType(DiscoveryLocation $location, DiscoveredType $type): void
    {
        $this->validator->assertNameAvailable($this->discoveryItems, $type);

        $this->discoveryItems->add($location, $type->withBindName());
    }

    /**
     * The Rebing field list a hand-written Rebing class belongs to; the discovered field wrappers have none.
     *
     * @param  ClassReflector<object>  $class
     * @return 'types'|'query'|'mutation'|null
     */
    private function rebingFieldType(ClassReflector $class): ?string
    {
        return match (true) {
            $class->is(RebingType::class) => 'types',
            $class->is(RebingQuery::class) && ! $class->is(QueryField::class) => 'query',
            $class->is(RebingMutation::class) && ! $class->is(MutationField::class) => 'mutation',
            default => null,
        };
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function discoverAction(DiscoveryLocation $location, ClassReflector $class, MethodReflector $method, Query|Mutation $action): void
    {
        $return = $this->returns->resolve($action, $class, $method);

        foreach ([...$method->getAttributes(ActionDecorator::class), ...$class->getAttributes(ActionDecorator::class)] as $decorator) {
            $decorator->decorate($action);
        }

        if ($action->name === null) {
            $operation = $this->names->name($this->names->operations(), $method->getName(), sprintf('the method %s::%s', $class->getName(), $method->getName()));
            $action->name = $operation === $method->getName() ? null : $operation;
        }

        $argProviders = array_values([
            ...$method->getAttributes(ActionArgProvider::class),
            ...$class->getAttributes(ActionArgProvider::class),
        ]);

        $parameters = $this->parameters->classify($class, $method, $argProviders);

        $middleware = [
            ...$this->middlewareOf($class),
            ...$this->middlewareOf($method),
        ];

        $authorizations = array_values([
            ...$class->getAttributes(Authorize::class),
            ...$method->getAttributes(Authorize::class),
        ]);

        Authorize::verifyOnAction($authorizations, $class->getName(), $method->getName(), class_basename($action::class));

        $discovered = new DiscoveredAction(
            action: $action,
            class: $class->getName(),
            method: $method->getName(),
            parameters: $parameters,
            middleware: $middleware,
            deprecationReason: DeprecationReason::from($method->getAttribute(Deprecated::class)),
            authorizations: $authorizations,
            typeBuilder: $return->typeBuilder,
            argProviders: $argProviders,
            returnType: $return->typeRef($action),
        );

        $this->addImplicitEnums($location, $this->usage->ofAction($discovered));
        $this->discoveryItems->add($location, $discovered->withBindName());
    }

    /**
     * @param  ClassReflector<object>|MethodReflector  $reflector
     * @return list<class-string<RebingMiddleware>>
     */
    private function middlewareOf(ClassReflector|MethodReflector $reflector): array
    {
        return array_merge(...array_map(
            static fn(Middleware $attribute): array => $attribute->middleware,
            $reflector->getAttributes(Middleware::class),
        ));
    }

    public function apply(): void
    {
        $replacements = Replacements::from($this->discoveryItems);
        $extensions = Extensions::from($this->discoveryItems, $replacements);
        $types = $extensions->extend($this->usage->typesToRegister($this->discoveryItems, $replacements));

        $this->bindSingletons($types);
        $this->registerTypes($types, $replacements);
        $this->registerProviders();
        $this->validator->validate($this->discoveryItems, $types);

        if (! $this->app->configurationIsCached()) {
            $this->writeConfig($types);
        }
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
     * @param  list<DiscoveredType>  $types
     */
    private function registerTypes(array $types, Replacements $replacements): void
    {
        $registry = $this->app->make(TypeRegistry::class);

        foreach ($types as $type) {
            $registry->register($type->class, $type->name, $type->kind);
            $registry->describe($type);
        }

        foreach ($replacements->pairs() as [$replaced, $replacement, $kind]) {
            $registry->replace($replaced, $replacement, $kind);
        }
    }

    private function registerProviders(): void
    {
        $registry = $this->app->make(TypeRegistry::class);

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredTypeProvider) {
                $registry->addProvider($item->class);
            }
        }
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

        foreach ($registered as $type) {
            $types[$type->name] = (string) $type->bindName;
        }

        foreach ($this->discoveryItems as $item) {
            if ($item instanceof DiscoveredAction && $item->bindName !== null) {
                $fieldName = $item->action->name ?? $item->method;
                $schemas[$item->action->schema ?? $defaultSchema][$item->fieldType][$fieldName] = $item->bindName;
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
}
