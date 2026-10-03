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
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Context;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredArg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredFlattenedInput;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredModelBinding;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
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
        private TypeMapperRegistry $mappers,
        private InputCollector $inputs,
    ) {}

    /**
     * @param  ClassReflector<object>  $class
     * @param  list<ActionArgProvider>  $argProviders
     */
    public function classify(ClassReflector $class, MethodReflector $method, array $argProviders = []): ClassifiedParameters
    {
        $providerArgs = $this->providerArgOwners($argProviders, $class, $method);
        $valueObjectClasses = $this->collectValueObjectClasses($argProviders);

        $args = [];
        $injections = [];
        $containerInjections = [];
        $argCompositions = [];
        $modelBindings = [];
        $flattenedInputs = [];

        foreach ($method->getParameters() as $param) {
            // Resolved by $container->call() through the attribute's resolve() hook.
            if ($param->getAttribute(ContextualAttribute::class) !== null) {
                continue;
            }

            /** @var Arg|null $argAttr */
            $argAttr = $param->getAttribute(Arg::class);

            if ($param->getAttribute(AsArgs::class) !== null) {
                $flattenedInputs[] = $this->discoverFlattenedInput($argAttr, $param, $class, $method);
                continue;
            }

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

            if (! $type->isScalar() && $this->isInput($type->getName())) {
                $args[] = $this->discoverInputArg($argAttr, $param, $class, $method);
                continue;
            }

            if (!$argAttr && !$type->isScalar() && ! enum_exists($type->getName())) {
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
        $this->assertNoFlattenedArgCollisions($flattenedInputs, $args, $modelBindings, $providerArgs, $class, $method);

        return new ClassifiedParameters(
            args: $args,
            injections: $injections,
            containerInjections: $containerInjections,
            argCompositions: $argCompositions,
            modelBindings: $modelBindings,
            flattenedInputs: $flattenedInputs,
        );
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function discoverFlattenedInput(?Arg $argAttr, ParameterReflector $param, ClassReflector $class, MethodReflector $method): DiscoveredFlattenedInput
    {
        $where = sprintf('#[AsArgs] on the parameter $%s in %s::%s', $param->getName(), $class->getName(), $method->getName());
        $type = $param->getType();

        if ($argAttr !== null) {
            throw new LogicException("$where cannot be combined with #[Arg]: the parameter has no arg of its own to name or describe. Remove #[Arg], and rename or describe the fields with #[Field(name:, description:)] on the #[Input] class.");
        }

        if ($param->getAttributes(Authorize::class) !== []) {
            throw new LogicException("$where cannot be combined with #[Authorize]: the parameter binds no record of its own. Put #[Authorize('ability')] on the model property of the #[Input] class instead.");
        }

        $input = $type->isClass() ? $type->asClass() : null;

        if ($input === null || ! Input::marks($input->getName())) {
            $kind = $this->nonInputKind($param);

            throw new LogicException($kind === null
                ? sprintf('%s needs an #[Input] class, but $%s is typed %s. Add #[Input] to %s, or remove #[AsArgs].', $where, $param->getName(), $type->getName(), class_basename($type->getName()))
                : sprintf('%s is not supported: $%s is %s, and #[AsArgs] only applies to a parameter typed as an #[Input] class. Remove #[AsArgs].', $where, $param->getName(), $kind));
        }

        if ($type->isNullable() || $param->hasDefaultValue()) {
            throw new LogicException("$where is not supported on a parameter that is nullable or has a default: flattened args cannot say the input as a whole is absent. Make the parameter required, or drop #[AsArgs] to take a nullable input arg.");
        }

        /** @var Input $attribute */
        $attribute = $input->getAttribute(Input::class);

        return new DiscoveredFlattenedInput($param->getName(), $this->inputs->collect($input, $attribute));
    }

    /**
     * What a parameter that can never take #[Input] is, or null for a plain class that could.
     */
    private function nonInputKind(ParameterReflector $param): ?string
    {
        if ($this->detectInjectionKind($param) !== null) {
            return 'an injected value (#[Root], #[Context] or ResolveInfo)';
        }

        $type = $param->getType();

        return match (true) {
            interface_exists($type->getName()) => "the interface {$type->getName()}",
            ! $type->isClass() => "of type {$type->getName()}",
            $type->isEnum() => "the enum {$type->getName()}",
            is_a($type->getName(), EloquentModel::class, true) => "the Eloquent model {$type->getName()}, which binds by ID",
            default => null,
        };
    }

    /**
     * Every flattened field must own its arg name: no other arg, model binding, arg provider or #[AsArgs] may take it.
     *
     * @param  list<DiscoveredFlattenedInput>  $flattenedInputs
     * @param  list<DiscoveredArg>  $args
     * @param  list<DiscoveredModelBinding>  $modelBindings
     * @param  array<string, string>  $providerArgs  how to name each arg provider's arg, keyed by arg name
     * @param  ClassReflector<object>  $class
     */
    private function assertNoFlattenedArgCollisions(array $flattenedInputs, array $args, array $modelBindings, array $providerArgs, ClassReflector $class, MethodReflector $method): void
    {
        if ($flattenedInputs === []) {
            return;
        }

        $owners = $providerArgs;

        foreach ($args as $arg) {
            $owners[$arg->name] = "the arg of the parameter \${$arg->paramName}";
        }

        foreach ($modelBindings as $binding) {
            $owners[$binding->argName] = "the arg of the model-bound parameter \${$binding->paramName}";
        }

        foreach ($flattenedInputs as $flattened) {
            foreach ($flattened->type->fields as $field) {
                $owner = $owners[$field->name] ?? null;

                if ($owner !== null) {
                    throw new LogicException(sprintf(
                        '#[AsArgs] on the parameter $%s in %s::%s flattens %s::$%s into the arg "%s", which collides with %s. Rename the field with #[Field(name: ...)], or rename the other arg.',
                        $flattened->paramName,
                        $class->getName(),
                        $method->getName(),
                        $flattened->type->class,
                        $field->phpName,
                        $field->name,
                        $owner,
                    ));
                }

                $owners[$field->name] = sprintf('%s::$%s, flattened by #[AsArgs] on $%s', $flattened->type->class, $field->phpName, $flattened->paramName);
            }
        }
    }

    /**
     * Each arg provider's args, named for error messages; two providers may not declare one arg.
     *
     * @param  list<ActionArgProvider>  $argProviders
     * @param  ClassReflector<object>  $class
     * @return array<string, string>
     */
    private function providerArgOwners(array $argProviders, ClassReflector $class, MethodReflector $method): array
    {
        $owners = [];

        foreach ($argProviders as $provider) {
            foreach (array_keys($provider->provideArgs()) as $name) {
                if (isset($owners[$name])) {
                    throw new RuntimeException(sprintf(
                        'Method %s::%s has multiple ActionArgProvider attributes declaring the same arg "%s".',
                        $class->getName(),
                        $method->getName(),
                        $name,
                    ));
                }

                $owners[(string) $name] = sprintf('the arg #[%s] adds', class_basename($provider::class));
            }
        }

        return $owners;
    }

    /**
     * @param  list<ActionArgProvider>  $argProviders
     * @return array<class-string<ComposedFromArgs>, class-string<ComposedFromArgs>>
     */
    private function collectValueObjectClasses(array $argProviders): array
    {
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
     * @return list<Authorize>
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

            if ($authorize->onDenied !== null) {
                throw new LogicException(sprintf(
                    '#[Authorize(onDenied:)] on the parameter $%s in %s::%s only applies to a field of a #[Type]. A denied parameter always reports an error; remove onDenied:.',
                    $param->getName(),
                    $class->getName(),
                    $method->getName(),
                ));
            }

            $authorizations[] = $authorize;
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

    private function isInput(string $class): bool
    {
        return Input::marks($class);
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function discoverInputArg(?Arg $argAttr, ParameterReflector $param, ClassReflector $class, MethodReflector $method): DiscoveredArg
    {
        if ($argAttr?->type !== null) {
            throw new LogicException(sprintf(
                '#[Arg(type:)] on the parameter $%s in %s::%s is not supported: its type is the input type of the #[Input] %s. Remove type:.',
                $param->getName(),
                $class->getName(),
                $method->getName(),
                class_basename($param->getType()->getName()),
            ));
        }

        $hasDefault = $param->hasDefaultValue();

        return new DiscoveredArg(
            name: $argAttr !== null && $argAttr->name !== null ? $argAttr->name : $param->getName(),
            paramName: $param->getName(),
            type: $param->getType()->getName(),
            nullable: $param->getType()->isNullable() || $hasDefault,
            description: $argAttr?->description,
            hasRules: $argAttr !== null && ! empty($argAttr->rules),
            hasDefault: $hasDefault,
            defaultValue: $hasDefault ? $param->getDefaultValue() : null,
            deprecationReason: $argAttr?->deprecationReason,
            input: true,
        );
    }

    /**
     * @param ClassReflector<object> $class
     */
    private function discoverArg(?Arg $argAttr, ParameterReflector $param, ClassReflector $class, MethodReflector $method): DiscoveredArg
    {
        $typeReflector = $param->getType();
        $hasDefault = $param->hasDefaultValue();
        $nullable = $typeReflector->isNullable() || $hasDefault;
        $mapped = null;

        if ($argAttr !== null && $argAttr->type !== null) {
            $typeName = $argAttr->type;
        } elseif ($typeReflector->getName() !== 'mixed' && ($mapped = $this->mappers->map($typeReflector, $this->member($param, $method))) !== null) {
            if ($mapped->nullable && ! $nullable) {
                throw new LogicException(sprintf(
                    'A type mapper makes the argument $%s in %s::%s nullable (%s), but the parameter accepts no null. Make the parameter nullable or give it a default.',
                    $param->getName(),
                    $class->getName(),
                    $method->getName(),
                    $mapped->target(),
                ));
            }

            $mapped = $mapped->orNullable($nullable);
            $typeName = $mapped->target();
        } elseif ($typeReflector->isScalar() || enum_exists($typeReflector->getName())) {
            $typeName = $typeReflector->getName();
        } else {
            throw new RuntimeException(sprintf(
                'Parameter $%s in %s::%s is not a scalar or enum type. Use #[Arg(type: \'GraphQLTypeName\')] to specify the GraphQL type.',
                $param->getName(),
                $class->getName(),
                $method->getName(),
            ));
        }

        $hasRules = $argAttr !== null && ! empty($argAttr->rules);

        return new DiscoveredArg(
            name: $argAttr !== null && $argAttr->name !== null ? $argAttr->name : $param->getName(),
            paramName: $param->getName(),
            type: $typeName,
            nullable: $nullable,
            description: $argAttr?->description,
            hasRules: $hasRules,
            hasDefault: $hasDefault,
            defaultValue: $hasDefault ? $param->getDefaultValue() : null,
            deprecationReason: $argAttr?->deprecationReason,
            typeRef: $mapped,
        );
    }

    private function member(ParameterReflector $param, MethodReflector $method): Member
    {
        return new Member(
            $param->getName(),
            $method->getReflection()->getDeclaringClass()->getName(),
            Position::Input,
            MemberKind::Parameter,
        );
    }
}
