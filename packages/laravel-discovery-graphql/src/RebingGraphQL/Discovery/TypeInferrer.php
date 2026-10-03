<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\OmittableType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Tempest\Reflection\TypeReflector;
use Traversable;

/** Infers the GraphQL output type of a PHP type, following the inference table. */
final readonly class TypeInferrer
{
    private const array SCALARS = ['string', 'int', 'float', 'bool'];

    public function __construct(
        private TypeMapperRegistry $mappers,
    ) {}

    /**
     * @param  class-string  $declaringClass  what `self` and `static` refer to
     * @param  string  $label  how errors name the member, e.g. "Property Book::$title"
     * @param  string  $attribute  the attribute errors point at for type: and of:
     * @param  Member|null  $member  the member the type mappers are asked about, if any
     */
    public function output(
        ?ReflectionType $type,
        string $declaringClass,
        string $label,
        string $attribute,
        ?string $explicitType = null,
        ?string $of = null,
        bool $nullable = false,
        bool $nullableItems = false,
        ?Member $member = null,
    ): TypeRef {
        if (OmittableType::of($type) !== null) {
            throw new LogicException(Input::marks($declaringClass)
                ? sprintf('%s is typed %s, but %s is both a #[Type] and an #[Input], and Omitted has no meaning in output position. Remove Omitted, or declare the partial update as its own #[Input] class.', $label, $type, class_basename($declaringClass))
                : sprintf('%s is typed %s, but Omitted only applies to a property of an #[Input] class, in input position. Remove Omitted from the type.', $label, $type));
        }

        $this->assertNotBothTypeAndOf($explicitType, $of, $label, $attribute);

        $nullable = $nullable || $this->allowsNull($type);

        if ($of !== null) {
            return TypeRef::from($of, list: true, nullable: $nullable, nullableItems: $nullableItems);
        }

        if ($explicitType !== null) {
            return TypeRef::from($explicitType, nullable: $nullable);
        }

        if ($member !== null && ($ref = $this->map($type, $member)) !== null) {
            return $ref->orNullable($nullable);
        }

        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            throw new LogicException(sprintf(
                '%s has the %s type %s, which has no GraphQL type. Name one with #[%s(type: ...)]; union output types need a #[Union] marker.',
                $label,
                $type instanceof ReflectionUnionType ? 'union' : 'intersection',
                $type,
                $attribute,
            ));
        }

        if (! $type instanceof ReflectionNamedType || $type->getName() === 'mixed') {
            throw new LogicException(sprintf(
                '%s declares %s. Add a PHP type, or name the GraphQL type with #[%s(type: ...)].',
                $label,
                $type === null ? 'no type' : 'the type mixed',
                $attribute,
            ));
        }

        $name = in_array($type->getName(), ['self', 'static'], true) ? $declaringClass : $type->getName();

        if (in_array($name, self::SCALARS, true)) {
            return TypeRef::scalar($name, nullable: $nullable);
        }

        $isClass = class_exists($name) || interface_exists($name) || enum_exists($name);

        if (in_array($name, ['array', 'iterable'], true) || ($isClass && is_a($name, Traversable::class, true))) {
            throw new LogicException(sprintf(
                '%s has type %s, which needs #[%s(of: ...)] to name the type of its items, or #[%s(type: ...)] to name its GraphQL type.',
                $label,
                $type,
                $attribute,
                $attribute,
            ));
        }

        if (! $isClass) {
            throw new LogicException(sprintf(
                '%s has type %s, which has no GraphQL output type. Name one with #[%s(type: ...)].',
                $label,
                $type,
                $attribute,
            ));
        }

        return TypeRef::class($name, nullable: $nullable);
    }

    /** Rejects a member that sets both `type:` and `of:`. */
    public function assertNotBothTypeAndOf(?string $type, ?string $of, string $label, string $attribute): void
    {
        if ($type !== null && $of !== null) {
            throw new LogicException(sprintf(
                '%s sets both type: and of: on #[%s]. Use of: for a list of that type, or type: for a single value.',
                $label,
                $attribute,
            ));
        }
    }

    /**
     * Infers the GraphQL input type of a PHP type that is not an Eloquent model.
     *
     * @param  class-string  $declaringClass
     */
    public function input(
        ?ReflectionType $type,
        string $declaringClass,
        string $label,
        string $attribute,
        ?string $explicitType = null,
        ?string $of = null,
        bool $nullable = false,
        bool $nullableItems = false,
        ?Member $member = null,
    ): TypeRef {
        if ($explicitType === null && $of === null) {
            if ($member !== null && ! $this->isInputClass($type) && ($mapped = $this->map($type, $member)) !== null) {
                return $mapped->orNullable($nullable || $this->allowsNull($type));
            }

            $this->assertInputShape($type, $label, $attribute);
        }

        $ref = $this->output($type, $declaringClass, $label, $attribute, $explicitType, $of, $nullable, $nullableItems);

        if ($ref->class !== null) {
            $this->assertInputClass($ref->class, $label, $attribute);
        }

        return $ref;
    }

    /** An #[Input] class is always its own input type, never a mapper's. */
    private function isInputClass(?ReflectionType $type): bool
    {
        return $type instanceof ReflectionNamedType && Input::marks($type->getName());
    }

    /** Untyped and `mixed` members are never offered to the mappers. */
    private function map(?ReflectionType $type, Member $member): ?TypeRef
    {
        if ($type === null || ($type instanceof ReflectionNamedType && $type->getName() === 'mixed')) {
            return null;
        }

        return $this->mappers->map(new TypeReflector($type), $member);
    }

    private function assertInputShape(?ReflectionType $type, string $member, string $attribute): void
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            throw new LogicException(sprintf(
                '%s has the %s type %s, which has no GraphQL input type. Use a single type, or name one with #[%s(type: ...)].',
                $member,
                $type instanceof ReflectionUnionType ? 'union' : 'intersection',
                $type,
                $attribute,
            ));
        }

        if (! $type instanceof ReflectionNamedType || ! $type->isBuiltin()) {
            return;
        }

        if (! in_array($type->getName(), [...self::SCALARS, 'array', 'iterable', 'mixed'], true)) {
            throw new LogicException(sprintf(
                '%s has type %s, which has no GraphQL input type. Name one with #[%s(type: ...)].',
                $member,
                $type,
                $attribute,
            ));
        }
    }

    /**
     * @param  class-string  $class
     */
    private function assertInputClass(string $class, string $member, string $attribute): void
    {
        if (enum_exists($class) || Input::marks($class)) {
            return;
        }

        $reflection = new ReflectionClass($class);

        $problem = match (true) {
            $reflection->isInterface() => 'an interface, which has no GraphQL input type',
            is_a($class, EloquentModel::class, true) => 'an Eloquent model, which is only bound as a single ID; a list of models is not supported',
            $reflection->getAttributes(Type::class) !== [] => sprintf('an output-only #[Type]. Add #[Input] to %s to accept it as input too', $reflection->getShortName()),
            default => null,
        };

        if ($problem === null) {
            return;
        }

        throw new LogicException(sprintf(
            '%s references %s, which is %s. Use a scalar, an enum or an #[Input] class, or name a registered GraphQL input type with #[%s(type: ...)].',
            $member,
            $class,
            $problem,
            $attribute,
        ));
    }

    private function allowsNull(?ReflectionType $type): bool
    {
        return $type !== null
            && $type->allowsNull()
            && ! ($type instanceof ReflectionNamedType && $type->getName() === 'mixed');
    }
}
