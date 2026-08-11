<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

/**
 * Deliberately does NOT implement ComposedFromArgs — it stands in for a spatie/laravel-data object,
 * hydrated only because a tagged Hydrator claims it.
 */
final readonly class PlainCursor
{
    public function __construct(
        public string $cursor = '',
    ) {}
}
