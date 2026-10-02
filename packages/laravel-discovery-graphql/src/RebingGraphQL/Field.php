<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

/** Configures a property or method of a #[Type] class as a GraphQL field. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Field
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $of = null,
        public bool $nullable = false,
        public bool $nullableItems = false,
        public ?string $description = null,
        public ?string $deprecationReason = null,
    ) {}
}
