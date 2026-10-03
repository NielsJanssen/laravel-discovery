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
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchedFieldDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchLoader;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\VerifiesLoadOptions;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\Naming;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use PropertyHookType;
use Rebing\GraphQL\Support\Type as RebingType;
use ReflectionClass;
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
        private SkippedMembers $skipped,
        private FieldMembers $members,
        private Naming $names,
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

        $source = sprintf('#[Type(naming:)] on %s', $class->getName());
        $fieldNaming = $this->names->fields($type->naming, $source);
        $argumentNaming = $this->names->arguments($type->naming, $source);

        return new DiscoveredType(
            name: $type->name ?? $this->typeName($class),
            class: $class->getName(),
            kind: TypeKind::Object,
            description: $type->description,
            fields: $this->fields($class, $fieldNaming, $argumentNaming),
        );
    }

    /**
     * @param  ClassReflector<object>  $class
     * @return list<DiscoveredTypeField>
     */
    private function fields(ClassReflector $class, NamingStrategy $fieldNaming, NamingStrategy $argumentNaming): array
    {
        $fields = [];

        foreach ($class->getProperties() as $property) {
            $field = $this->includes($property) ? $this->propertyField($class, $property, $fieldNaming) : null;

            if ($field === null) {
                $this->assertUndecorated($property);
            } else {
                $fields[] = $field;
            }
        }

        foreach ($class->getReflection()->getMethods() as $reflection) {
            $method = new MethodReflector($reflection);

            $field = $this->includes($method) ? $this->methodField($class, $method, $fieldNaming, $argumentNaming) : null;

            if ($field === null) {
                $this->assertUndecorated($method);
            } else {
                $fields[] = $field;
            }
        }

        $this->members->assertUniqueNames('Type', $class->getName(), $fields);

        return $fields;
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function propertyField(ClassReflector $class, PropertyReflector $property, NamingStrategy $naming): ?DiscoveredTypeField
    {
        $member = $this->members->member($property);

        if ($member === null) {
            return null;
        }

        $reflection = $property->getReflection();
        $field = $member->field;
        $label = $member->label;

        if (! $reflection->isVirtual() && $class->is(EloquentModel::class)) {
            if ($field === null && $this->skipped->isImportedFromTrait($reflection)) {
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

        if ($member->lacksHook(PropertyHookType::Get)) {
            return $member->skipOrReject('no get hook, so it cannot be read.');
        }

        if ($field?->rules !== null && ! $class->hasAttribute(Input::class)) {
            throw new LogicException("$label has #[Field(rules:)], but rules only apply to a property of an #[Input]. Add #[Input] to the class, or remove rules:.");
        }

        return $this->decorate($label, $property, new DiscoveredTypeField(
            phpName: $property->getName(),
            name: $this->members->name($member, $naming),
            type: $this->inferType($reflection->getType(), $class, $label, $field, new Member($property->getName(), $reflection->getDeclaringClass()->getName(), Position::Output, MemberKind::Property)),
            source: FieldSource::Property,
            description: $field?->description,
            deprecationReason: $field?->deprecationReason,
            typeClass: $class->getName(),
        ));
    }

    /**
     * @param  ClassReflector<object>  $class
     */
    private function methodField(ClassReflector $class, MethodReflector $method, NamingStrategy $naming, NamingStrategy $argumentNaming): ?DiscoveredTypeField
    {
        $member = $this->members->member($method);
        $field = $member?->field;

        if ($member === null || $field === null) {
            return null;
        }

        $reflection = $method->getReflection();
        $label = $member->label;

        $this->assertNoActionAttributes($label, $method);

        if ($field->rules !== null) {
            throw new LogicException("$label has #[Field(rules:)], but rules only apply to a property of an #[Input]. Remove rules:.");
        }

        $parameters = $this->parameters->classify($class, $method, naming: $argumentNaming);

        $this->assertResolvableParameters($label, $parameters);

        return $this->decorate($label, $method, new DiscoveredTypeField(
            phpName: $method->getName(),
            name: $this->members->name($member, $naming),
            type: $this->inferType($reflection->getReturnType(), $class, $label, $field, new Member($method->getName(), $reflection->getDeclaringClass()->getName(), Position::Output, MemberKind::MethodReturn)),
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
        return ! $this->skipped->skips($member);
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
        $flattened = $parameters->flattenedInputs[0] ?? null;

        if ($flattened !== null) {
            throw new LogicException(sprintf(
                '%s has #[AsArgs] on $%s, which #[Field] methods do not support yet: field args are neither validated, hydrated nor authorized. Take scalar args instead, or move the operation to a #[Query] or #[Mutation].',
                $member,
                $flattened->paramName,
            ));
        }

        foreach ($parameters->args as $arg) {
            if ($arg->input) {
                throw new LogicException(sprintf(
                    '%s takes the #[Input] %s as $%s, which fields do not support yet. Take its values as scalar args instead.',
                    $member,
                    class_basename($arg->type->target()),
                    $arg->paramName,
                ));
            }

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
     */
    private function typeName(ClassReflector $class): string
    {
        $name = $class->getShortName();

        return str_ends_with($name, 'Type') && $name !== 'Type' ? substr($name, 0, -4) : $name;
    }
}
