<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

final readonly class PlainFilter
{
    public function __construct(
        public ?string $term = null,
    ) {}
}
