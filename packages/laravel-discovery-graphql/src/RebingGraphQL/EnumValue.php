<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

/** Describes one case of a GraphQL enum. */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class EnumValue
{
    public function __construct(
        public ?string $description = null,
    ) {}
}
