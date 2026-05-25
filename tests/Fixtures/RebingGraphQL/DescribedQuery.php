<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class DescribedQuery
{
    #[Query(name: 'described', description: 'Returns a greeting')]
    public function resolve(string $name): string
    {
        return "Hi, {$name}";
    }
}
