<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;

/** Marks a class as a GraphQL object type; its public properties and #[Field] methods become fields. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Type
{
    /**
     * @param  FieldCase|class-string<NamingStrategy>|null  $naming  names this type's fields and field args instead of the configured strategies
     * @param  class-string<TypeFactory>|null  $factory  adds fields when the type is built
     * @param  bool  $replace  takes over the GraphQL name of the nearest parent class that is a discovered type
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public FieldCase|string|null $naming = null,
        public ?string $factory = null,
        public bool $replace = false,
    ) {}
}
