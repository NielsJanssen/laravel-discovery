<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use Countable;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class InterfaceProperty
{
    public Countable $items;
}
