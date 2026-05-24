<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class OptionalArgQuery
{
    #[Query(name: 'add')]
    public function resolve(int $a = 0, int $b = 0): int
    {
        return $a + $b;
    }
}
