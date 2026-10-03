<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

#[Schema('admin')]
class SchemaQueries
{
    #[Query(name: 'classLevelQuery')]
    public function classLevelQuery(): string
    {
        return 'ok';
    }

    #[Mutation(name: 'classLevelMutation')]
    public function classLevelMutation(): string
    {
        return 'ok';
    }

    #[Query(name: 'methodWins')]
    #[Schema('public')]
    public function methodWins(): string
    {
        return 'ok';
    }

    #[Query(name: 'explicitWins', schema: 'reports')]
    #[Schema('public')]
    public function explicitWins(): string
    {
        return 'ok';
    }
}
