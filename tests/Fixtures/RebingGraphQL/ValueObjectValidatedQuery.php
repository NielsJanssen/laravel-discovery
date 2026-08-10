<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ValueObjectValidatedQuery
{
    #[Query(name: 'valueObjectValidated')]
    #[TestPaged]
    public function resolve(TestPage $page): string
    {
        return "offset {$page->offset}";
    }
}
