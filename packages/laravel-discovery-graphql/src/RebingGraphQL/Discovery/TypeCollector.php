<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Deprecated;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Action;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionArgProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionTypeBuilder;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredTypeField;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDecoratorReference;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDiscoveryVerifier;
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

        $decorator = $class->getAttribute(FieldDecorator::class);

        if ($decorator !== null && ! $this->hasActions($class)) {
            throw new LogicException(sprintf(
                '#[%s] on the #[Type] class %s has nothing to apply to: on a class it only reaches #[Query] and #[Mutation] methods, never fields. Put it on each property or #[Field] method it should guard.',
                class_basename($decorator::class),
                $class->getName(),
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
            if (! $this->includes($property)) {
                continue;
            }

            $field = $this->propertyField($class, $property);

            if ($field === null) {
                $this->assertUndecorated($property);
            } else {
                $fields[] = $field;
            }
        }

        foreach ($class->getReflection()->getMethods() as $reflection) {
            $method = new MethodReflector($reflection);

            if (! $this->includes($method)) {
                continue;
            }

            $field = $this->methodField($class, $method);

            if ($field === null) {
                $this->assertUndecorated($method);
            } else {
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

        return $this->decorate($member, $property, new DiscoveredTypeField(
            phpName: $property->getName(),
            name: $field->name ?? $this->fieldName($property),
            type: $this->inferType($reflection->getType(), $class, $member, $field),
            source: FieldSource::Property,
            description: $field?->description,
            deprecationReason: $field?->deprecationReason,
        ));
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

        return $this->decorate($member, $method, new DiscoveredTypeField(
            phpName: $method->getName(),
            name: $field->name ?? $this->fieldName($method),
            type: $this->inferType($reflection->getReturnType(), $class, $member, $field),
            source: FieldSource::Method,
            parameters: $parameters,
            description: $field->description,
            deprecationReason: $field->deprecationReason ?? DeprecationReason::from($method->getAttribute(Deprecated::class)),
        ));
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
     * Attaches the field's decorators, each checked against the field first.
     */
    private function decorate(string $label, PropertyReflector|MethodReflector $member, DiscoveredTypeField $field): DiscoveredTypeField
    {
        $decorators = $member->getAttributes(FieldDecorator::class);

        foreach ($decorators as $decorator) {
            if ($decorator instanceof FieldDiscoveryVerifier) {
                $decorator->verify($label, $field);
            }
        }

        return $decorators === [] ? $field : $field->withDecorators(array_map(
            static fn(FieldDecorator $decorator, int $index) => FieldDecoratorReference::storable($decorator, $field->source, $field->phpName, $index),
            $decorators,
            array_keys($decorators),
        ));
    }

    /**
     * A field decorator on a member that is not a field would be silently ignored.
     */
    private function assertUndecorated(PropertyReflector|MethodReflector $member): void
    {
        $decorator = $member->getAttribute(FieldDecorator::class);

        if ($decorator === null) {
            return;
        }

        $name = class_basename($decorator::class);
        $class = $member->getReflection()->getDeclaringClass()->getName();

        if ($member instanceof PropertyReflector) {
            throw new LogicException(sprintf(
                'Property %s::$%s has #[%s] but is not a field, because it is ignored, not public, static or has no get hook. Make it a field, or remove #[%s].',
                $class,
                $member->getName(),
                $name,
                $name,
            ));
        }

        if ($member->getAttribute(Action::class) === null) {
            throw new LogicException(sprintf(
                'Method %s::%s() has #[%s] but is not a field. Add #[Field], or remove #[%s].',
                $class,
                $member->getName(),
                $name,
                $name,
            ));
        }
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function hasActions(ClassReflector $class): bool
    {
        return array_any($class->getPublicMethods(), static fn(MethodReflector $method): bool => $method->getAttribute(Action::class) !== null);
    }

    private function assertNoActionAttributes(string $member, MethodReflector $method): void
    {
        foreach ([ActionArgProvider::class, ActionTypeBuilder::class, ActionDecorator::class] as $contract) {
            foreach ($method->getAttributes($contract) as $attribute) {
                if ($attribute instanceof FieldDecorator) {
                    continue;
                }

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
