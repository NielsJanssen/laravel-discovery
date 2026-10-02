<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(name: 'Pamphlet')]
final class ClashesWithRebingType
{
    public string $title = 'title';
}
