<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class NonScalarQuery
{
    #[Query(type: 'Book', list: true)]
    public function resolve(array $missingArg): array
    {
        return [];
    }
}
