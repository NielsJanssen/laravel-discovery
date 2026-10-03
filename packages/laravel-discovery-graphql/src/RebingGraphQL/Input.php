<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use ReflectionClass;

/** Marks a class as a GraphQL input object type; its public properties become input fields. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Input
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}

    /** Whether the class carries #[Input]. */
    public static function marks(string $class): bool
    {
        return class_exists($class) && new ReflectionClass($class)->getAttributes(self::class) !== [];
    }
}
