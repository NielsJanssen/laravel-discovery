<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sort;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sortable;

class PaginatedBooksQuery
{
    #[Query(name: 'paginatedBooks', type: 'Book')]
    #[Paginated]
    #[Sortable(['id', 'title', 'author'])]
    public function resolve(Pagination $pagination, Sort $sort): LengthAwarePaginator
    {
        $books = [
            ['id' => 1, 'title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald'],
            ['id' => 2, 'title' => '1984', 'author' => 'George Orwell'],
            ['id' => 3, 'title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee'],
        ];

        if ($sort->field !== null) {
            usort($books, fn($a, $b) => $sort->direction === 'desc'
                ? $b[$sort->field] <=> $a[$sort->field]
                : $a[$sort->field] <=> $b[$sort->field]);
        }

        $offset = ($pagination->page - 1) * $pagination->limit;

        return new LengthAwarePaginatorImpl(
            items: array_slice($books, $offset, $pagination->limit),
            total: count($books),
            perPage: $pagination->limit,
            currentPage: $pagination->page,
        );
    }
}
