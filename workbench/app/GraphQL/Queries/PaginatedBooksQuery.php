<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImpl;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class PaginatedBooksQuery
{
    #[Query(name: 'paginatedBooks', type: 'Book')]
    #[Paginated]
    public function resolve(
        int $page = 1,
        int $perPage = 2,
    ): LengthAwarePaginator {
        $books = [
            ['id' => 1, 'title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald'],
            ['id' => 2, 'title' => '1984', 'author' => 'George Orwell'],
            ['id' => 3, 'title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee'],
        ];

        $offset = ($page - 1) * $perPage;

        return new LengthAwarePaginatorImpl(
            items: array_slice($books, $offset, $perPage),
            total: count($books),
            perPage: $perPage,
            currentPage: $page,
        );
    }
}
