<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/** A PHP type that includes Omitted, split into the one type it makes optional and whether it accepts null. */
final readonly class OmittableType
{
    /**
     * @param  ReflectionNamedType|null  $inner  null when Omitted joins no other type, or more than one
     * @param  list<ReflectionType>  $others  every type besides Omitted and null
     */
    private function __construct(
        public ?ReflectionNamedType $inner,
        public bool $allowsNull,
        public array $others,
    ) {}

    /** Null when the type does not include Omitted. */
    public static function of(?ReflectionType $type): ?self
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName() === Omitted::class ? new self(null, $type->allowsNull(), []) : null;
        }

        if (! $type instanceof ReflectionUnionType) {
            return null;
        }

        $omitted = false;
        $allowsNull = false;
        $others = [];

        foreach ($type->getTypes() as $member) {
            $name = $member instanceof ReflectionNamedType ? $member->getName() : null;

            if ($name === Omitted::class) {
                $omitted = true;
            } elseif ($name === 'null') {
                $allowsNull = true;
            } else {
                $others[] = $member;
            }
        }

        if (! $omitted) {
            return null;
        }

        $inner = count($others) === 1 && $others[0] instanceof ReflectionNamedType ? $others[0] : null;

        return new self($inner, $allowsNull, $others);
    }
}
