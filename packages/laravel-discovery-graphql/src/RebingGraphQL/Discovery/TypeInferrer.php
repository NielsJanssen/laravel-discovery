<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use LogicException;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
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
        if ($explicitType !== null && $of !== null) {
            throw new LogicException(sprintf(
                '%s sets both type: and of: on #[%s]. Use of: for a list of that type, or type: for a single value.',
                $label,
                $attribute,
            ));
        }

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

    /** Untyped and `mixed` members are never offered to the mappers. */
    private function map(?ReflectionType $type, Member $member): ?TypeRef
    {
        if ($type === null || ($type instanceof ReflectionNamedType && $type->getName() === 'mixed')) {
            return null;
        }

        return $this->mappers->map(new TypeReflector($type), $member);
    }

    private function allowsNull(?ReflectionType $type): bool
    {
        return $type !== null
            && $type->allowsNull()
            && ! ($type instanceof ReflectionNamedType && $type->getName() === 'mixed');
    }
}
