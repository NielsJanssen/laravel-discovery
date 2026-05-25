<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

class SchemaOnMethodQuery
{
    #[Query(name: 'methodLevel')]
    #[Schema('admin')]
    public function resolve(): string
    {
        return 'ok';
    }
}
