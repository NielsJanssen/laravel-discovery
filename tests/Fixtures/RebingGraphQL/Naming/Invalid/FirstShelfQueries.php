<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class FirstShelfQueries
{
    #[Query]
    public function shelfCount(): int
    {
        return 1;
    }

    #[Mutation(name: 'shelfCount')]
    public function recount(): int
    {
        return 1;
    }

    #[Query(schema: 'archive')]
    public function archivedShelfCount(): int
    {
        return 1;
    }
}
