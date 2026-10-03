<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final readonly class PageCursor
{
    public function __construct(
        public int $page = 1,
    ) {}
}
