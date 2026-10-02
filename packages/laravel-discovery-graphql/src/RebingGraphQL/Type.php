<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

/** Marks a class as a GraphQL object type; its public properties and #[Field] methods become fields. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Type
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
