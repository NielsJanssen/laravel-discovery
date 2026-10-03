<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class CrateQueries
{
    #[Query(of: Crate::class)]
    public function crates(): array
    {
        return [new Crate('a'), new Crate('b')];
    }
}
