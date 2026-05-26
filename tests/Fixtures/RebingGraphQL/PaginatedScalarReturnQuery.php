<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class PaginatedScalarReturnQuery
{
    #[Query(name: 'paginatedScalar')]
    #[Paginated]
    public function resolve(): string
    {
        return 'unused';
    }
}
