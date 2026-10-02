<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ListOfQuery
{
    /** @return list<string> */
    #[Query(of: 'string')]
    public function tags(): array
    {
        return ['classic', 'novel'];
    }

    /** @return list<?string> */
    #[Query(of: 'string', nullableItems: true)]
    public function sparseTags(): array
    {
        return ['classic', null];
    }

    /** @return list<Book> */
    #[Query(of: Book::class)]
    public function books(): array
    {
        return [new Book()];
    }

    /** @return list<Book>|null */
    #[Query(of: 'Book')]
    public function maybeBooks(): ?array
    {
        return null;
    }

    #[Query(type: Book::class)]
    public function book(): ?Book
    {
        return new Book();
    }

    #[Query(type: 'ID')]
    public function bookId(): string
    {
        return '1';
    }
}
