<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/**
 * One #[Authorize('ability')] read off a model-bound parameter, where the ability is always present.
 */
readonly class DiscoveredModelAuthorization
{
    public const DEFAULT_MESSAGE = 'Forbidden';

    public function __construct(
        public string $ability,
        public string $message = self::DEFAULT_MESSAGE,
    ) {}
}
