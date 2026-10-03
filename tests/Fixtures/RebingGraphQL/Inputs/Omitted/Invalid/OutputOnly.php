<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class OutputOnly
{
    public string|Omitted $title = Omitted::Value;
}
