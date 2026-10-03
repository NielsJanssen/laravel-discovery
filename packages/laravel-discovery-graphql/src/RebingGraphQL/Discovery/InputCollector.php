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
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\OmittableType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
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
        private FieldMembers $members,
        private Naming $names,
    ) {}

    /**
     * @param  ClassReflector<object>  $class
     * @param  NamingStrategy|null  $naming  names the fields instead of #[Input(naming:)] and the field strategy, as for #[AsArgs]
     */
    public function collect(ClassReflector $class, Input $input, ?NamingStrategy $naming = null): DiscoveredType
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

        if ($input->factory !== null) {
            throw new LogicException(sprintf('#[Input(factory:)] on %s is not supported yet: a type factory only contributes fields to a #[Type]. Remove factory:.', $class->getName()));
        }

        Replacements::assertDeclarable($class, TypeKind::Input, $input->replace, $input->name !== null);

        $shared = $class->hasAttribute(Type::class);

        if (! $shared) {
            $this->assertNoMethodFields($class);
        }

        $naming ??= $this->names->fields($input->naming, sprintf('#[Input(naming:)] on %s', $class->getName()));
        $fields = $this->fields($class, $shared, $naming);

        $this->assertConstructible($class, $fields);

        return new DiscoveredType(
            name: $input->name ?? $this->inputName($class),
            class: $class->getName(),
            kind: TypeKind::Input,
            description: $input->description,
            fields: $fields,
            replace: $input->replace,
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
    private function fields(ClassReflector $class, bool $shared, NamingStrategy $naming): array
    {
        $fields = [];

        foreach ($class->getProperties() as $property) {
            $field = $this->skipped->skips($property) ? null : $this->propertyField($class, $property, $shared, $naming);

            if ($field !== null) {
                $fields[] = $field;
            }
        }

        $this->members->assertUniqueNames('Input', $class->getName(), $fields);

        return $fields;
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function propertyField(ClassReflector $class, PropertyReflector $property, bool $shared, NamingStrategy $naming): ?DiscoveredTypeField
    {
        $member = $this->members->member($property);

        if ($member === null) {
            return null;
        }

        if ($member->lacksHook(PropertyHookType::Set)) {
            return $shared ? null : $member->skipOrReject('no set hook, so an input cannot fill it.');
        }

        $reflection = $property->getReflection();
        $field = $member->field;
        $label = $member->label;

        $defaults = $this->defaultOf($reflection);
        [$hasDefault, $default] = $defaults;
        $name = $this->members->name($member, $naming);
        $type = $reflection->getType();
        $omittable = OmittableType::of($type);

        if ($omittable !== null) {
            $type = $this->omittableInner($class, $label, (string) $type, $omittable, $shared, $defaults);
        }

        $nullable = ($field !== null && $field->nullable) || $hasDefault || ($type?->allowsNull() ?? false);
        $authorizations = array_values($property->getAttributes(Authorize::class));

        $modelClass = $type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), EloquentModel::class, true)
            ? $type->getName()
            : null;

        $binding = null;

        if ($modelClass !== null && $field?->of === null) {
            foreach ($authorizations as $authorize) {
                $authorize->verifyOnProperty($label, $shared);
            }

            $binding = new DiscoveredModelBinding(
                paramName: $property->getName(),
                argName: $name,
                modelClass: $modelClass,
                nullable: $omittable === null ? $nullable : $omittable->allowsNull,
                type: $field?->type,
                hasUserRules: $field?->hasRules() ?? false,
                authorizations: $authorizations,
            );

            $ref = TypeRef::from($field->type ?? 'ID', nullable: $nullable);
        } else {
            if ($authorizations !== [] && ! $shared) {
                throw new LogicException(sprintf(
                    '#[Authorize] on %s only applies to a property that binds an Eloquent model; $%s does not.',
                    lcfirst($label),
                    $property->getName(),
                ));
            }

            $ref = $this->inferrer->input(
                $type,
                $class->getName(),
                $label,
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
            hasRules: $field?->hasRules() ?? false,
            binding: $binding,
            hasDefault: $hasDefault,
            defaultValue: $omittable === null && $this->isPrintable($default) ? $default : null,
            omittable: $omittable !== null,
            rejectsNull: $omittable !== null && ! $omittable->allowsNull,
        );
    }

    /**
     * The one type an Omitted property makes optional; every other shape is rejected.
     *
     * @param  ClassReflector<object>  $class
     * @param  array{0: bool, 1: mixed}  $default  from defaultOf()
     */
    private function omittableInner(ClassReflector $class, string $member, string $type, OmittableType $omittable, bool $shared, array $default): ReflectionNamedType
    {
        [$hasDefault, $value] = $default;

        if ($shared) {
            throw new LogicException(sprintf(
                '%s is typed %s, but %s is both a #[Type] and an #[Input], and Omitted has no meaning in output position. Remove Omitted, or declare the partial update as its own #[Input] class.',
                $member,
                $type,
                $class->getShortName(),
            ));
        }

        if ($omittable->inner === null) {
            throw new LogicException($omittable->others === []
                ? sprintf('%s is typed %s, which leaves no value to send besides Omitted. Name the type Omitted makes optional, as in string|Omitted.', $member, $type)
                : sprintf('%s is typed %s, but Omitted makes exactly one type optional, as in string|Omitted or string|Omitted|null. Keep one type besides Omitted and null.', $member, $type));
        }

        if (! $hasDefault || $value !== Omitted::Value) {
            throw new LogicException(sprintf(
                '%s is typed %s, but %s, so a field the caller leaves out has nothing to hydrate to. Give it the default Omitted::Value.',
                $member,
                $type,
                $hasDefault ? 'its default is not Omitted::Value' : 'it has no default',
            ));
        }

        return $omittable->inner;
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
