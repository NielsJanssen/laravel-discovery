<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;
use ReflectionClass;

/** Marks a class as a GraphQL input object type; its public properties become input fields. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Input
{
    /**
     * @param  FieldCase|class-string<NamingStrategy>|null  $naming  names this input's fields instead of the configured strategy
     * @param  class-string<TypeFactory>|null  $factory  not supported yet: rejected at discovery
     * @param  bool  $replace  takes over the GraphQL name of the nearest parent class that is a discovered input
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public FieldCase|string|null $naming = null,
        public ?string $factory = null,
        public bool $replace = false,
    ) {}

    /** Whether the class carries #[Input]. */
    public static function marks(string $class): bool
    {
        return class_exists($class) && new ReflectionClass($class)->getAttributes(self::class) !== [];
    }
}
