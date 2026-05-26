<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class PaginatedBookQuery
{
    #[Query(type: 'Book', name: 'paginatedBooks')]
    #[Paginated]
    public function resolve(): LengthAwarePaginator
    {
        return new LengthAwarePaginatorImpl(items: [], total: 0, perPage: 10);
    }
}
