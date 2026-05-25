<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

#[Schema('admin')]
class SchemaExplicitArgQuery
{
    #[Query(name: 'explicitWins', schema: 'reports')]
    #[Schema('public')]
    public function resolve(): string
    {
        return 'ok';
    }
}
