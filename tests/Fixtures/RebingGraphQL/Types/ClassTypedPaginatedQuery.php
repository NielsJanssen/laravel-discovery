<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ClassTypedPaginatedQuery
{
    #[Query(name: 'bookPage', type: Book::class)]
    #[Paginated]
    public function resolve(): LengthAwarePaginator
    {
        return new LengthAwarePaginatorImpl(items: [], total: 0, perPage: 10);
    }
}
