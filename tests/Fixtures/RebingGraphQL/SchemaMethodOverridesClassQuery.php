<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

#[Schema('admin')]
class SchemaMethodOverridesClassQuery
{
    #[Query(name: 'methodWins')]
    #[Schema('public')]
    public function methodScoped(): string
    {
        return 'ok';
    }

    #[Query(name: 'classFallback')]
    public function classScoped(): string
    {
        return 'ok';
    }
}
