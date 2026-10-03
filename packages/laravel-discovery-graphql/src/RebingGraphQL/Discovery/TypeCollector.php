<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Deprecated;
use Illuminate\Database\Eloquent\Model as EloquentModel;
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
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchedFieldDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchLoader;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\VerifiesLoadOptions;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use PropertyHookType;
use Rebing\GraphQL\Support\Type as RebingType;
use ReflectionClass;
use ReflectionProperty;
use ReflectionType;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\PropertyReflector;
use Throwable;

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
            $field = $this->includes($property) ? $this->propertyField($class, $property) : null;

            if ($field === null) {
                $this->assertUndecorated($property);
            } else {
                $fields[] = $field;
            }
        }

        foreach ($class->getReflection()->getMethods() as $reflection) {
            $method = new MethodReflector($reflection);

            $field = $this->includes($method) ? $this->methodField($class, $method) : null;

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
        $label = sprintf('Property %s::$%s', $reflection->getDeclaringClass()->getName(), $property->getName());

        if ($property->hasAttribute(Ignore::class)) {
            return $field === null ? null : throw new LogicException("$label has both #[Field] and #[Ignore]. Remove one.");
        }

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            return $field === null ? null : throw new LogicException(sprintf(
                '%s has #[Field] but is %s. Only public, non-static properties become fields.',
                $label,
                $reflection->isStatic() ? 'static' : 'not public',
            ));
        }

        if (! $reflection->isVirtual() && $class->is(EloquentModel::class)) {
            if ($field === null && $this->isImportedFromTrait($reflection)) {
                return null;
            }

            throw new LogicException(sprintf(
                '%s is a plain public property on an Eloquent model, so it shadows the attribute \'%s\'. Make it a virtual hooked property, as in `public string $%s { get => $this->getAttribute(\'%s\'); }`, or add #[Ignore] to keep it out of the type.',
                $label,
                $property->getName(),
                $property->getName(),
                $property->getName(),
            ));
        }

        if ($reflection->isVirtual() && ! $reflection->hasHook(PropertyHookType::Get)) {
            return $field === null ? null : throw new LogicException("$label has #[Field] but no get hook, so it cannot be read.");
        }

        $member = new Member($property->getName(), $reflection->getDeclaringClass()->getName(), Position::Output, MemberKind::Property);

        return $this->decorate($label, $property, new DiscoveredTypeField(
            phpName: $property->getName(),
            name: $field->name ?? $this->fieldName($property),
            type: $this->inferType($reflection->getType(), $class, $label, $field, $member),
            source: FieldSource::Property,
            description: $field?->description,
            deprecationReason: $field?->deprecationReason,
            typeClass: $class->getName(),
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
        $label = sprintf('Method %s::%s()', $reflection->getDeclaringClass()->getName(), $method->getName());

        if ($method->hasAttribute(Ignore::class)) {
            throw new LogicException("$label has both #[Field] and #[Ignore]. Remove one.");
        }

        if (! $reflection->isPublic() || $reflection->isStatic()) {
            throw new LogicException(sprintf(
                '%s has #[Field] but is %s. Only public, non-static methods become fields.',
                $label,
                $reflection->isStatic() ? 'static' : 'not public',
            ));
        }

        $this->assertNoActionAttributes($label, $method);

        $parameters = $this->parameters->classify($class, $method);

        $this->assertResolvableParameters($label, $parameters);

        $member = new Member($method->getName(), $reflection->getDeclaringClass()->getName(), Position::Output, MemberKind::MethodReturn);

        return $this->decorate($label, $method, new DiscoveredTypeField(
            phpName: $method->getName(),
            name: $field->name ?? $this->fieldName($method),
            type: $this->inferType($reflection->getReturnType(), $class, $label, $field, $member),
            source: FieldSource::Method,
            parameters: $parameters,
            description: $field->description,
            deprecationReason: $field->deprecationReason ?? DeprecationReason::from($method->getAttribute(Deprecated::class)),
            typeClass: $class->getName(),
        ));
    }

    /**
     * Whether a member is considered for a field at all.
     */
    private function includes(PropertyReflector|MethodReflector $member): bool
    {
        $declaringClass = $member->getReflection()->getDeclaringClass();

        if (str_starts_with($declaringClass->getName(), 'Illuminate\\')) {
            return false;
        }

        return ! $member instanceof PropertyReflector || ! $this->isFrameworkProperty($declaringClass, $member->getName());
    }

    /**
     * Whether a parent class or trait under the Illuminate namespace declares the property as well.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function isFrameworkProperty(ReflectionClass $class, string $property): bool
    {
        $owners = trait_uses_recursive($class->getName());

        for ($parent = $class->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            $owners[] = $parent->getName();
        }

        foreach ($owners as $owner) {
            if (str_starts_with($owner, 'Illuminate\\') && property_exists($owner, $property)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a property comes from a trait rather than the class body.
     */
    private function isImportedFromTrait(ReflectionProperty $property): bool
    {
        foreach (trait_uses_recursive($property->getDeclaringClass()->getName()) as $trait) {
            if (property_exists($trait, $property->getName())) {
                return true;
            }
        }

        return false;
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
    private function inferType(?ReflectionType $type, ClassReflector $class, string $label, ?Field $field, Member $member): TypeRef
    {
        return $this->inferrer->output(
            $type,
            $class->getName(),
            $label,
            'Field',
            $field?->type,
            $field?->of,
            $field !== null && $field->nullable,
            $field !== null && $field->nullableItems,
            $member,
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

        $this->assertLoadable($label, $field, array_values(array_filter(
            $decorators,
            static fn(FieldDecorator $decorator): bool => $decorator instanceof BatchedFieldDecorator,
        )));

        return $decorators === [] ? $field : $field->withDecorators(array_map(
            static fn(FieldDecorator $decorator, int $index) => FieldDecoratorReference::storable($decorator, $field->source, $field->phpName, $index),
            $decorators,
            array_keys($decorators),
        ));
    }

    /**
     * A batched field has one loader, a BatchLoader, with options that survive the discovery cache.
     *
     * @param  list<BatchedFieldDecorator>  $batched
     */
    private function assertLoadable(string $label, DiscoveredTypeField $field, array $batched): void
    {
        if (count($batched) > 1) {
            throw new LogicException(sprintf(
                '%s has %s, but a field loads through one loader. Remove all but one.',
                $label,
                implode(' and ', array_map(static fn(BatchedFieldDecorator $attribute): string => '#[' . class_basename($attribute::class) . ']', $batched)),
            ));
        }

        $attribute = $batched[0] ?? null;

        if ($attribute === null) {
            return;
        }

        $name = '#[' . class_basename($attribute::class) . ']';
        $loader = $attribute->loader();

        if (! class_exists($loader) || ! in_array(BatchLoader::class, class_implements($loader), true)) {
            throw new LogicException(sprintf('%s has %s, whose loader %s does not implement %s.', $label, $name, $loader, BatchLoader::class));
        }

        if (! new ReflectionClass($loader)->isInstantiable()) {
            throw new LogicException(sprintf('%s has %s, whose loader %s cannot be instantiated. Name a concrete BatchLoader class.', $label, $name, $loader));
        }

        $options = $attribute->options($field->phpName);

        try {
            serialize($options);
        } catch (Throwable $exception) {
            throw new LogicException(sprintf(
                '%s has %s with an option that cannot be cached with discovery (%s). Options must be scalars, arrays, enums or other serializable values, never closures.',
                $label,
                $name,
                $exception->getMessage(),
            ), previous: $exception);
        }

        if (is_a($loader, VerifiesLoadOptions::class, true)) {
            $loader::verifyOptions($label, $name, $field, $options);
        }
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
                'Property %s::$%s has #[%s] but is not a field, because it is ignored, not public, static, has no get hook, or is a framework or trait property that types skip. Make it a field, or remove #[%s].',
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
