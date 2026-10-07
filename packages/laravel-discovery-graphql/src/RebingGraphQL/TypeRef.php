<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use InvalidArgumentException;
use ReflectionClass;

/**
 * A reference to a GraphQL type: a class-string, a GraphQL type name or a scalar name, plus its wrapping.
 */
final readonly class TypeRef
{
    /** PHP scalar names, `void`, and GraphQL's built-in scalar names. */
    public const array SCALARS = ['string', 'int', 'float', 'bool', 'void', 'ID', 'String', 'Int', 'Float', 'Boolean'];

    /**
     * @param class-string|null $class
     */
    private function __construct(
        public ?string $class = null,
        public ?string $name = null,
        public ?string $scalar = null,
        public bool $list = false,
        public bool $nullable = false,
        public bool $nullableItems = false,
    ) {}

    /**
     * @param class-string $class
     */
    public static function class(string $class, bool $list = false, bool $nullable = false, bool $nullableItems = false): self
    {
        if (! class_exists($class) && ! interface_exists($class) && ! enum_exists($class)) {
            throw new InvalidArgumentException("TypeRef::class() expects an existing class, interface or enum, got [$class].");
        }

        return new self(class: $class, list: $list, nullable: $nullable, nullableItems: $nullableItems);
    }

    public static function named(string $name, bool $list = false, bool $nullable = false, bool $nullableItems = false): self
    {
        return new self(name: $name, list: $list, nullable: $nullable, nullableItems: $nullableItems);
    }

    public static function scalar(string $scalar, bool $list = false, bool $nullable = false, bool $nullableItems = false): self
    {
        if (! in_array($scalar, self::SCALARS, true)) {
            throw new InvalidArgumentException(sprintf(
                'TypeRef::scalar() expects one of %s, got [%s].',
                implode(', ', self::SCALARS),
                $scalar,
            ));
        }

        return new self(scalar: $scalar, list: $list, nullable: $nullable, nullableItems: $nullableItems);
    }

    /**
     * Classify a `type:` or `of:` value as a scalar, a class-string or a GraphQL type name.
     */
    public static function from(string $type, bool $list = false, bool $nullable = false, bool $nullableItems = false): self
    {
        return match (true) {
            in_array($type, self::SCALARS, true) => self::scalar($type, $list, $nullable, $nullableItems),
            self::isClassName($type) => self::class($type, $list, $nullable, $nullableItems),
            default => self::named($type, $list, $nullable, $nullableItems),
        };
    }

    /**
     * Whether a string names a class, interface or enum by its own name. An alias, such as Laravel's `Date` facade
     * alias, is read as a GraphQL type name instead, since aliases share their short names with GraphQL types.
     *
     * @phpstan-assert-if-true class-string $name
     */
    public static function isClassName(string $name): bool
    {
        if (! class_exists($name) && ! interface_exists($name) && ! enum_exists($name)) {
            return false;
        }

        return strcasecmp(ltrim($name, '\\'), new ReflectionClass($name)->getName()) === 0;
    }

    /** A copy that is nullable when either this reference or the given flag is; nullability only widens. */
    public function orNullable(bool $nullable): self
    {
        return $nullable && ! $this->nullable ? clone($this, ['nullable' => true]) : $this;
    }

    /** A copy of the same target with the given list and nullability wrapping. */
    public function wrapped(bool $list, bool $nullable, bool $nullableItems): self
    {
        return clone($this, ['list' => $list, 'nullable' => $nullable, 'nullableItems' => $nullableItems]);
    }

    /** The class-string, scalar name or GraphQL type name this reference points at. */
    public function target(): string
    {
        return (string) ($this->class ?? $this->scalar ?? $this->name);
    }

}
