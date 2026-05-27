<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class PaginatedWithValueObjectQuery
{
    #[Query(type: 'Book', name: 'paginatedWithValueObject')]
    #[Paginated]
    public function resolve(Pagination $pagination): LengthAwarePaginator
    {
        return new LengthAwarePaginatorImpl(
            items: [],
            total: 0,
            perPage: $pagination->limit,
            currentPage: $pagination->page,
        );
    }
}
