<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class SecondShelfQueries
{
    #[Query(name: 'archivedShelfCount')]
    public function archived(): int
    {
        return 2;
    }

    #[Query]
    public function shelfCount(): int
    {
        return 2;
    }
}
