<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Reference;

final class Recommender
{
    /**
     * @return list<Book>
     */
    public function for(Book $book, int $limit): array
    {
        return array_slice([
            new Book('2', "{$book->title}: The Sequel", null, $book->genre, $book->author),
            new Book('3', "{$book->title}: The Prequel", null, $book->genre, $book->author),
        ], 0, $limit);
    }
}
