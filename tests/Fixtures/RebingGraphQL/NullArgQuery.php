<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class NullArgQuery
{
    #[Query(name: 'books', type: 'Book', list: true)]
    public function resolve(#[Arg(type: 'Null')] mixed $bad): array
    {
        return [];
    }
}
