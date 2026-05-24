<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class BooksQuery
{
    #[Query(type: 'Book', name: 'books', list: true)]
    public function resolve(
        #[Arg(description: 'Filter by title')]
        ?string $title = null,
    ): array {
        $books = [
            ['id' => 1, 'title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald'],
            ['id' => 2, 'title' => '1984', 'author' => 'George Orwell'],
            ['id' => 3, 'title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee'],
        ];

        if ($title !== null) {
            return array_values(array_filter($books, fn($book) => str_contains($book['title'], $title)));
        }

        return $books;
    }
}
