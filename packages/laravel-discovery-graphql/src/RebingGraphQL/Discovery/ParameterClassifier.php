<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionArgProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ComposedFromArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\HydratorRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Context;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredArg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredModelAuthorization;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredModelBinding;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use RuntimeException;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\ParameterReflector;

/** Sorts a resolver method's parameters into GraphQL args, injections, compositions and model bindings. */
final readonly class ParameterClassifier
{
    /** laravel-validation's #[Can]. */
    private const VALUE_AUTHORIZATION_RULE = 'NielsJanssen\\Laravel\\Validation\\Rule\\Can';

    public function __construct(
        private HydratorRegistry $hydrators,
    ) {}

    /**
     * @param  ClassReflector<object>  $class
     * @param  list<ActionArgProvider>  $argProviders
     */
    public function classify(ClassReflector $class, MethodReflector $method, array $argProviders = []): ClassifiedParameters
    {
        $valueObjectClasses = $this->collectValueObjectClasses($argProviders, $class, $method);

        $args = [];
        $injections = [];
        $containerInjections = [];
        $argCompositions = [];
        $modelBindings = [];

        foreach ($method->getParameters() as $param) {
            // Resolved by $container->call() through the attribute's resolve() hook.
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

            if (!$type->isScalar() && is_a($type->getName(), EloquentModel::class, true)) {
                $this->assertNoValueAuthorizationRule($param, $class, $method);

                $modelBindings[] = new DiscoveredModelBinding(
                    paramName: $param->getName(),
                    argName: $argAttr !== null && $argAttr->name !== null ? $argAttr->name : $param->getName(),
                    modelClass: $type->getName(),
                    nullable: $type->isNullable() || $param->hasDefaultValue(),
                    type: $argAttr?->type,
                    hasUserRules: $argAttr !== null && ! empty($argAttr->rules),
                    authorizations: $this->discoverParameterAuthorizations($param, $class, $method),
                );

                continue;
            }

            $this->assertNoParameterAuthorization($param, $class, $method);

            if (!$argAttr && !$type->isScalar()) {
                $typeName = $type->getName();

                $valueObject = $valueObjectClasses[$typeName] ?? null;

                if ($valueObject !== null && $this->hydrators->hydrates($valueObject)) {
                    $argCompositions[$param->getName()] = $valueObject;
                    continue;
                }

                if (class_exists($typeName) || interface_exists($typeName)) {
                    $containerInjections[$param->getName()] = $typeName;
                    continue;
                }
            }

            $args[] = $this->discoverArg($argAttr, $param, $class, $method);
        }

        $this->assertNoArgNameCollisions($args, $class, $method);

        return new ClassifiedParameters(
            args: $args,
            injections: $injections,
            containerInjections: $containerInjections,
            argCompositions: $argCompositions,
            modelBindings: $modelBindings,
        );
    }

    /**
     * @param  list<ActionArgProvider>  $argProviders
     * @param  ClassReflector<object>  $class
     * @return array<class-string<ComposedFromArgs>, class-string<ComposedFromArgs>>
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
                $valueObjectClasses[$valueObject] = $valueObject;
            }
        }

        return $valueObjectClasses;
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

    /**
     * Matched by class name, so laravel-validation stays a suggestion rather than a dependency.
     *
     * @param ClassReflector<object> $class
     */
    private function assertNoValueAuthorizationRule(ParameterReflector $param, ClassReflector $class, MethodReflector $method): void
    {
        foreach ($param->getReflection()->getAttributes() as $attribute) {
            if ($attribute->getName() !== self::VALUE_AUTHORIZATION_RULE) {
                continue;
            }

            throw new LogicException(sprintf(
                'Validation attribute #[Can] on the model-bound parameter $%s in %s::%s would authorize the raw id, not the %s it binds. Use #[Authorize(\'ability\')] on the parameter instead.',
                $param->getName(),
                $class->getName(),
                $method->getName(),
                class_basename($param->getType()->getName()),
            ));
        }
    }

    /**
     * @param ClassReflector<object> $class
     * @return list<DiscoveredModelAuthorization>
     */
    private function discoverParameterAuthorizations(ParameterReflector $param, ClassReflector $class, MethodReflector $method): array
    {
        $authorizations = [];

        foreach ($param->getAttributes(Authorize::class) as $authorize) {
            if ($authorize->ability === null) {
                throw new LogicException(sprintf(
                    '#[Authorize] on the parameter $%s in %s::%s needs an ability, as in #[Authorize(\'view\')]. Bare #[Authorize] and #[Authorize(gate:)] belong on the class or the method.',
                    $param->getName(),
                    $class->getName(),
                    $method->getName(),
                ));
            }

            if ($authorize->gate !== null) {
                throw new LogicException(sprintf(
                    '#[Authorize(gate:)] on the parameter $%s in %s::%s is not supported: a gate class receives the raw args, so it belongs on the class or the method.',
                    $param->getName(),
                    $class->getName(),
                    $method->getName(),
                ));
            }

            $authorizations[] = new DiscoveredModelAuthorization(
                $authorize->ability,
                $authorize->message ?? DiscoveredModelAuthorization::DEFAULT_MESSAGE,
            );
        }

        return $authorizations;
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function assertNoParameterAuthorization(ParameterReflector $param, ClassReflector $class, MethodReflector $method): void
    {
        if ($param->getAttributes(Authorize::class) === []) {
            return;
        }

        throw new LogicException(sprintf(
            '#[Authorize] on the parameter $%s in %s::%s only applies to a model-bound parameter; $%s does not bind an Eloquent model.',
            $param->getName(),
            $class->getName(),
            $method->getName(),
            $param->getName(),
        ));
    }

    /**
     * @param  list<DiscoveredArg>  $args
     * @param  ClassReflector<object>  $class
     */
    private function assertNoArgNameCollisions(array $args, ClassReflector $class, MethodReflector $method): void
    {
        $paramNames = [];

        foreach ($method->getParameters() as $param) {
            $paramNames[$param->getName()] = true;
        }

        foreach ($args as $arg) {
            if ($arg->name === $arg->paramName) {
                continue;
            }

            if (isset($paramNames[$arg->name])) {
                throw new LogicException(sprintf(
                    'Argument #[Arg(name: \'%s\')] on %s::%s($%s) collides with the parameter $%s. Rename the arg or the parameter.',
                    $arg->name,
                    $class->getName(),
                    $method->getName(),
                    $arg->paramName,
                    $arg->name,
                ));
            }
        }
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function discoverArg(?Arg $argAttr, ParameterReflector $param, ClassReflector $class, MethodReflector $method): DiscoveredArg
    {
        $typeReflector = $param->getType();

        if ($argAttr !== null && $argAttr->type !== null) {
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

        $hasRules = $argAttr !== null && ! empty($argAttr->rules);
        $hasDefault = $param->hasDefaultValue();

        return new DiscoveredArg(
            name: $argAttr !== null && $argAttr->name !== null ? $argAttr->name : $param->getName(),
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
