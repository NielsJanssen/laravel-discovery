<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class TypeAndOfQuery
{
    /** @return list<Book> */
    #[Query(type: 'Book', of: 'Book')]
    public function books(): array
    {
        return [];
    }
}
