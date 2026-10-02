<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use InvalidArgumentException;

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
            class_exists($type), interface_exists($type), enum_exists($type) => self::class($type, $list, $nullable, $nullableItems),
            default => self::named($type, $list, $nullable, $nullableItems),
        };
    }

    public static function fromAction(Action $action): self
    {
        $type = $action->of ?? $action->type;

        if ($type === null) {
            throw new InvalidArgumentException('Cannot reference the type of an action whose type was not resolved.');
        }

        return self::from($type, $action->list, $action->nullable, $action->nullableItems);
    }
}
