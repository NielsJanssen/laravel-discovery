<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class ArrayWithoutOf
{
    /** @var list<string> */
    public array $tags = [];
}
