<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

/** Registers a PHP enum as a GraphQL enum, also when nothing references it. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Enum
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
