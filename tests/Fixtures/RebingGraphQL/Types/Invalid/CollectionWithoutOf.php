<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use Illuminate\Support\Collection;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class CollectionWithoutOf
{
    /** @var Collection<int, string>|null */
    public ?Collection $tags = null;
}
