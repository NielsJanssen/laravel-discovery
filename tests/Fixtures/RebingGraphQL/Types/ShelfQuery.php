<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ShelfQuery
{
    #[Query(type: Shelf::class)]
    public function shelf(): Shelf
    {
        return new Shelf('Fiction');
    }
}
