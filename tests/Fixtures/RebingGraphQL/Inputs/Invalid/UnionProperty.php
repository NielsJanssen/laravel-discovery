<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class UnionProperty
{
    public int|string $value = 0;
}
