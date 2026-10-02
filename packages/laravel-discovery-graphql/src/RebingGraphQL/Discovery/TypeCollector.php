<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Deprecated;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionArgProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionTypeBuilder;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldSource;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use PropertyHookType;
use Rebing\GraphQL\Support\Type as RebingType;
use ReflectionType;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\PropertyReflector;

/** Reads a #[Type] class's members into a DiscoveredType. */
final readonly class TypeCollector
{
    public function __construct(
        private ParameterClassifier $parameters,
        private TypeInferrer $inferrer,
    ) {}

    /**
     * @param  ClassReflector<object>  $class
     */
    public function collect(ClassReflector $class, Type $type): DiscoveredType
    {
        if ($class->getReflection()->isEnum()) {
            throw new LogicException(sprintf(
                '#[Type] on the enum %s is not supported: an enum is not an object type.',
                $class->getName(),
            ));
        }

        if ($class->is(RebingType::class)) {
            throw new LogicException(sprintf(
                '#[Type] on %s, which extends %s: a class is either a hand-written Rebing type or a #[Type], not both. Remove one.',
                $class->getName(),
                RebingType::class,
            ));
        }

        return new DiscoveredType(
            name: $type->name ?? $this->typeName($class),
            class: $class->getName(),
            kind: TypeKind::Object,
            description: $type->description,
            fields: $this->fields($class),
        );
    }

    /**
     * @param  ClassReflector<object>  $class
     * @return list<DiscoveredTypeField>
     */
    private function fields(ClassReflector $class): array
    {
        $fields = [];

        foreach ($class->getProperties() as $property) {
            if ($this->includes($property) && ($field = $this->propertyField($class, $property)) !== null) {
                $fields[] = $field;
            }
        }

        foreach ($class->getReflection()->getMethods() as $reflection) {
            $method = new MethodReflector($reflection);

            if ($this->includes($method) && ($field = $this->methodField($class, $method)) !== null) {
                $fields[] = $field;
            }
        }

        $this->assertUniqueFieldNames($class, $fields);

        return $fields;
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function propertyField(ClassReflector $class, PropertyReflector $property): ?DiscoveredTypeField
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

        if ($reflection->isVirtual() && ! $reflection->hasHook(PropertyHookType::Get)) {
            return $field === null ? null : throw new LogicException("$member has #[Field] but no get hook, so it cannot be read.");
        }

        return new DiscoveredTypeField(
            phpName: $property->getName(),
            name: $field->name ?? $this->fieldName($property),
            type: $this->inferType($reflection->getType(), $class, $member, $field),
            source: FieldSource::Property,
            description: $field?->description,
            deprecationReason: $field?->deprecationReason,
            decorators: $this->decorators($property),
        );
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function methodField(ClassReflector $class, MethodReflector $method): ?DiscoveredTypeField
    {
        $field = $method->getAttribute(Field::class);

        if ($field === null) {
            return null;
        }

        $reflection = $method->getReflection();
        $member = sprintf('Method %s::%s()', $reflection->getDeclaringClass()->getName(), $method->getName());

        if ($method->hasAttribute(Ignore::class)) {
            throw new LogicException("$member has both #[Field] and #[Ignore]. Remove one.");
        }

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            throw new LogicException(sprintf(
                '%s has #[Field] but is %s. Only public, non-static methods become fields.',
                $member,
                $reflection->isStatic() ? 'static' : 'not public',
            ));
        }

        $this->assertNoActionAttributes($member, $method);

        $parameters = $this->parameters->classify($class, $method);

        $this->assertResolvableParameters($member, $parameters);

        return new DiscoveredTypeField(
            phpName: $method->getName(),
            name: $field->name ?? $this->fieldName($method),
            type: $this->inferType($reflection->getReturnType(), $class, $member, $field),
            source: FieldSource::Method,
            parameters: $parameters,
            description: $field->description,
            deprecationReason: $field->deprecationReason ?? DeprecationReason::from($method->getAttribute(Deprecated::class)),
            decorators: $this->decorators($method),
        );
    }

    /**
     * Whether a member is considered for a field at all.
     */
    private function includes(PropertyReflector|MethodReflector $member): bool
    {
        return true;
    }

    /**
     * The GraphQL name of a field without an explicit name.
     */
    private function fieldName(PropertyReflector|MethodReflector $member): string
    {
        return $member->getName();
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function inferType(?ReflectionType $type, ClassReflector $class, string $member, ?Field $field): TypeRef
    {
        return $this->inferrer->output(
            $type,
            $class->getName(),
            $member,
            'Field',
            $field?->type,
            $field?->of,
            $field !== null && $field->nullable,
            $field !== null && $field->nullableItems,
        );
    }

    /**
     * Attribute instances that adjust a field when its definition is built.
     *
     * @return list<object>
     */
    private function decorators(PropertyReflector|MethodReflector $member): array
    {
        return [];
    }

    private function assertNoActionAttributes(string $member, MethodReflector $method): void
    {
        foreach ([ActionArgProvider::class, ActionTypeBuilder::class, ActionDecorator::class] as $contract) {
            $attribute = $method->getAttribute($contract);

            if ($attribute !== null) {
                throw new LogicException(sprintf(
                    '%s has #[%s], which only applies to #[Query] and #[Mutation] methods.',
                    $member,
                    class_basename($attribute::class),
                ));
            }
        }
    }

    private function assertResolvableParameters(string $member, ClassifiedParameters $parameters): void
    {
        foreach ($parameters->args as $arg) {
            if ($arg->hasRules) {
                throw new LogicException(sprintf(
                    '%s has #[Arg(rules:)] on $%s, but field args are not validated yet. Validate the value inside the method instead.',
                    $member,
                    $arg->paramName,
                ));
            }
        }

        $binding = $parameters->modelBindings[0] ?? null;

        if ($binding !== null) {
            throw new LogicException(sprintf(
                '%s binds the model parameter $%s, which fields do not support yet. Take the key as #[Arg(type: \'ID\')] string $%s and load the model inside the method.',
                $member,
                $binding->paramName,
                $binding->paramName,
            ));
        }
    }

    /**
     * @param  ClassReflector<object>  $class
     * @param  list<DiscoveredTypeField>  $fields
     */
    private function assertUniqueFieldNames(ClassReflector $class, array $fields): void
    {
        $seen = [];

        foreach ($fields as $field) {
            $previous = $seen[$field->name] ?? null;

            if ($previous !== null) {
                throw new LogicException(sprintf(
                    'Type %s has two fields named "%s" (%s and %s). Rename one with #[Field(name: ...)], or #[Ignore] one.',
                    $class->getName(),
                    $field->name,
                    $this->describe($previous),
                    $this->describe($field),
                ));
            }

            $seen[$field->name] = $field;
        }
    }

    private function describe(DiscoveredTypeField $field): string
    {
        return $field->source === FieldSource::Method ? "{$field->phpName}()" : "\${$field->phpName}";
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function typeName(ClassReflector $class): string
    {
        $name = $class->getShortName();

        return str_ends_with($name, 'Type') && $name !== 'Type' ? substr($name, 0, -4) : $name;
    }
}
