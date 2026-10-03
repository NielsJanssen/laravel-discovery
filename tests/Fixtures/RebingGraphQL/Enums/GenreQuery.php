<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class GenreQuery
{
    #[Query]
    public function genre(): Genre
    {
        return Genre::NonFiction;
    }

    /**
     * @return list<Genre>
     */
    #[Query(of: Genre::class)]
    public function genres(): array
    {
        return [Genre::Fiction, Genre::NonFiction];
    }

    #[Query]
    public function describeGenre(Genre $genre): string
    {
        return $genre::class . '::' . $genre->name;
    }
}
