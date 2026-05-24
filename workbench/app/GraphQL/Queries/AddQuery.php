<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class AddQuery
{
    #[Query(name: 'add')]
    public function resolve(int $a = 0, int $b = 0): int
    {
        return $a + $b;
    }
}
