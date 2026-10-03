<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class PaginatedInvalidReturns
{
    /** @return LengthAwarePaginator<int, mixed> */
    #[Query(name: 'paginatedContract')]
    #[Paginated]
    public function contractReturn(): LengthAwarePaginator
    {
        return new LengthAwarePaginatorImpl([], 0, 20);
    }

    #[Query(name: 'paginatedScalar')]
    #[Paginated]
    public function scalarReturn(): string
    {
        return 'unused';
    }
}
