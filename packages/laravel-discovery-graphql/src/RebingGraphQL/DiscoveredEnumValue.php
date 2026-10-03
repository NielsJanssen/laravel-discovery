<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** One case of a discovered enum, as a serializable description. */
final readonly class DiscoveredEnumValue
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?string $deprecationReason = null,
    ) {}
}
