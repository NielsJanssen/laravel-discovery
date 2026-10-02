<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class IterableWithoutOf
{
    /** @return iterable<string> */
    #[Field]
    public function tags(): iterable
    {
        return [];
    }
}
