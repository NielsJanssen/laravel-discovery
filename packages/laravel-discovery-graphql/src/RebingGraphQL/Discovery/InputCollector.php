<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredModelBinding;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use PropertyHookType;
use Rebing\GraphQL\Support\Type as RebingType;
use ReflectionNamedType;
use ReflectionProperty;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\PropertyReflector;
use UnitEnum;

/** Reads an #[Input] class's public properties into a DiscoveredType of kind input. */
final readonly class InputCollector
{
    public function __construct(
        private TypeInferrer $inferrer,
        private SkippedMembers $skipped,
    ) {}

    /**
     * @param  ClassReflector<object>  $class
     */
    public function collect(ClassReflector $class, Input $input): DiscoveredType
    {
        $reflection = $class->getReflection();

        if ($reflection->isEnum()) {
            throw new LogicException(sprintf(
                '#[Input] on the enum %s is not supported: an enum is not an input object type. Use the enum as a property or parameter type instead.',
                $class->getName(),
            ));
        }

        if (! $reflection->isInstantiable()) {
            throw new LogicException(sprintf(
                '#[Input] on %s, which cannot be instantiated: an input is hydrated into a new instance. Make it a concrete class with a public constructor.',
                $class->getName(),
            ));
        }

        if ($class->is(RebingType::class)) {
            throw new LogicException(sprintf(
                '#[Input] on %s, which extends %s: a class is either a hand-written Rebing type or an #[Input], not both. Remove one.',
                $class->getName(),
                RebingType::class,
            ));
        }

        if ($class->is(EloquentModel::class)) {
            throw new LogicException(sprintf(
                '#[Input] on the Eloquent model %s is not supported: in input position a model is always bound by its ID. Type a parameter or an input property as %s to bind one, or declare a separate #[Input] class with the values to fill it with.',
                $class->getName(),
                $class->getShortName(),
            ));
        }

        $shared = $class->hasAttribute(Type::class);

        if (! $shared) {
            $this->assertNoMethodFields($class);
        }

        $fields = $this->fields($class, $shared);

        $this->assertConstructible($class, $fields);

        return new DiscoveredType(
            name: $input->name ?? $this->inputName($class),
            class: $class->getName(),
            kind: TypeKind::Input,
            description: $input->description,
            fields: $fields,
        );
    }

    /**
     * Every constructor parameter the hydrator may leave out must have a default or accept null.
     *
     * @param  ClassReflector<object>  $class
     * @param  list<DiscoveredTypeField>  $fields
     */
    private function assertConstructible(ClassReflector $class, array $fields): void
    {
        $byProperty = [];

        foreach ($fields as $field) {
            $byProperty[$field->phpName] = $field;
        }

        foreach ($class->getReflection()->getConstructor()?->getParameters() ?? [] as $parameter) {
            if ($parameter->isDefaultValueAvailable() || $parameter->allowsNull()) {
                continue;
            }

            $name = $parameter->getName();
            $field = $byProperty[$name] ?? null;

            if ($field === null) {
                throw new LogicException(sprintf(
                    'Constructor parameter $%s of the #[Input] %s has no default and no input field to fill it from, so the input cannot be built. Make it a public promoted property, give it a default, or make it nullable.',
                    $name,
                    $class->getName(),
                ));
            }

            if ($field->type->nullable) {
                throw new LogicException(sprintf(
                    'Property %s::$%s is optional in the input (through a property default, #[Field(nullable: true)] or a type mapper), but its constructor parameter has no default and accepts no null, so an absent value cannot build the input. Give $%s a default, make it nullable, or keep the field required.',
                    $class->getName(),
                    $name,
                    $name,
                ));
            }
        }
    }

    /**
     * @param  ClassReflector<object>  $class
     * @return list<DiscoveredTypeField>
     */
    private function fields(ClassReflector $class, bool $shared): array
    {
        $fields = [];
        $seen = [];

        foreach ($class->getProperties() as $property) {
            $field = $this->skipped->skips($property) ? null : $this->propertyField($class, $property, $shared);

            if ($field === null) {
                continue;
            }

            $previous = $seen[$field->name] ?? null;

            if ($previous !== null) {
                throw new LogicException(sprintf(
                    'Input %s has two fields named "%s" ($%s and $%s). Rename one with #[Field(name: ...)], or #[Ignore] one.',
                    $class->getName(),
                    $field->name,
                    $previous,
                    $field->phpName,
                ));
            }

            $seen[$field->name] = $field->phpName;
            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function propertyField(ClassReflector $class, PropertyReflector $property, bool $shared): ?DiscoveredTypeField
    {
        $reflection = $property->getReflection();
        $field = $property->getAttribute(Field::class);
        $member = sprintf('Property %s::$%s', $reflection->getDeclaringClass()->getName(), $property->getName());

        if ($property->hasAttribute(Ignore::class)) {
            return $field === null ? null : throw new LogicException("$member has both #[Field] and #[Ignore]. Remove one.");
        }

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            return $field === null ? null : throw new LogicException(sprintf(
                '%s has #[Field] but is %s. Only public, non-static properties become fields.',
                $member,
                $reflection->isStatic() ? 'static' : 'not public',
            ));
        }

        if ($reflection->isVirtual() && ! $reflection->hasHook(PropertyHookType::Set)) {
            if ($shared) {
                return null;
            }

            return $field === null ? null : throw new LogicException("$member has #[Field] but no set hook, so an input cannot fill it.");
        }

        [$hasDefault, $default] = $this->defaultOf($reflection);
        $name = $field->name ?? $property->getName();
        $type = $reflection->getType();
        $nullable = ($field !== null && $field->nullable) || $hasDefault || ($type?->allowsNull() ?? false);
        $authorizations = array_values($property->getAttributes(Authorize::class));
        $modelClass = $type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), EloquentModel::class, true)
            ? $type->getName()
            : null;

        $binding = null;

        if ($modelClass !== null && $field?->of === null) {
            $binding = new DiscoveredModelBinding(
                paramName: $property->getName(),
                argName: $name,
                modelClass: $modelClass,
                nullable: $nullable,
                type: $field?->type,
                hasUserRules: $this->hasRules($field),
                authorizations: $this->authorizations($authorizations, $member, $shared),
            );

            $ref = TypeRef::from($field->type ?? 'ID', nullable: $nullable);
        } else {
            if ($authorizations !== [] && ! $shared) {
                throw new LogicException(sprintf(
                    '#[Authorize] on %s only applies to a property that binds an Eloquent model; $%s does not.',
                    lcfirst($member),
                    $property->getName(),
                ));
            }

            $ref = $this->inferrer->input(
                $type,
                $class->getName(),
                $member,
                'Field',
                $field?->type,
                $field?->of,
                $nullable,
                $field !== null && $field->nullableItems,
                new Member($property->getName(), $reflection->getDeclaringClass()->getName(), Position::Input, MemberKind::Property),
            );
        }

        return new DiscoveredTypeField(
            phpName: $property->getName(),
            name: $name,
            type: $ref,
            description: $field?->description,
            deprecationReason: $field?->deprecationReason,
            hasRules: $this->hasRules($field),
            binding: $binding,
            hasDefault: $hasDefault,
            defaultValue: $this->isPrintable($default) ? $default : null,
        );
    }

    /**
     * A promoted property keeps its default on the constructor parameter.
     *
     * @return array{0: bool, 1: mixed}
     */
    private function defaultOf(ReflectionProperty $property): array
    {
        if ($property->isPromoted()) {
            foreach ($property->getDeclaringClass()->getConstructor()?->getParameters() ?? [] as $parameter) {
                if ($parameter->getName() === $property->getName()) {
                    return $parameter->isDefaultValueAvailable() ? [true, $parameter->getDefaultValue()] : [false, null];
                }
            }
        }

        return $property->hasDefaultValue() && $property->hasType() ? [true, $property->getDefaultValue()] : [false, null];
    }

    private function isPrintable(mixed $value): bool
    {
        if (is_array($value)) {
            return array_all($value, $this->isPrintable(...));
        }

        return is_scalar($value) || $value instanceof UnitEnum;
    }

    private function hasRules(?Field $field): bool
    {
        return $field !== null && $field->rules !== null && $field->rules !== [];
    }

    /**
     * @param  list<Authorize>  $authorizations
     * @return list<Authorize>
     */
    private function authorizations(array $authorizations, string $member, bool $shared): array
    {
        foreach ($authorizations as $authorize) {
            if ($authorize->ability === null) {
                throw new LogicException(sprintf(
                    "#[Authorize] on %s needs an ability, as in #[Authorize('view')], to check the record it binds.",
                    lcfirst($member),
                ));
            }

            if ($authorize->gate !== null) {
                throw new LogicException(sprintf(
                    '#[Authorize(gate:)] on %s is not supported: a gate class receives the raw args, so it belongs on the action.',
                    lcfirst($member),
                ));
            }

            if ($authorize->onDenied !== null && ! $shared) {
                throw new LogicException(sprintf(
                    '#[Authorize(onDenied:)] on %s only applies to a field of a #[Type]. A denied input always reports an error; remove onDenied:.',
                    lcfirst($member),
                ));
            }
        }

        return $authorizations;
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function assertNoMethodFields(ClassReflector $class): void
    {
        foreach ($class->getReflection()->getMethods() as $method) {
            if ($method->getAttributes(Field::class) !== [] && ! $this->skipped->skips(new MethodReflector($method))) {
                throw new LogicException(sprintf(
                    'Method %s::%s() has #[Field], but an #[Input] takes its fields from properties only. Make it a property, or add #[Type] to %s for an output field.',
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $class->getShortName(),
                ));
            }
        }
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function inputName(ClassReflector $class): string
    {
        $name = $class->getShortName();

        return str_ends_with($name, 'Input') ? $name : $name . 'Input';
    }
}
