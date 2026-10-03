<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type, Input]
final class Shared
{
    public function __construct(
        public string|Omitted $title = Omitted::Value,
    ) {}
}
