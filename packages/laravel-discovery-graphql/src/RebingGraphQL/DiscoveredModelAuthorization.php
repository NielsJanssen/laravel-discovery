<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/**
 * One #[Authorize('ability')] read off a model-bound parameter, where the ability is always present.
 */
readonly class DiscoveredModelAuthorization
{
    public function __construct(
        public string $ability,
        public ?string $message = null,
    ) {}
}
