<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final readonly class Sorting
{
    public function __construct(
        public string $sortBy = 'title',
        public bool $descending = false,
    ) {}
}
