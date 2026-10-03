<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;

#[Input]
final readonly class WideUnion
{
    public function __construct(
        public string|int|Omitted $value = Omitted::Value,
    ) {}
}
