<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AuthorQueries
{
    /**
     * @return list<Author>
     */
    #[Query(of: Author::class)]
    public function allAuthors(): array
    {
        return Author::query()->orderBy('id')->get()->all();
    }
}
