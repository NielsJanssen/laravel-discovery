<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class TraitPaginatedQueries
{
    use PaginatesBooks;

    #[Query(type: 'Book', name: 'traitPaginatedBooks')]
    public function books(Pagination $pagination): LengthAwarePaginator
    {
        return new LengthAwarePaginatorImpl(items: [], total: 0, perPage: $pagination->limit, currentPage: $pagination->page);
    }
}
