<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

#[Schema('admin')]
class SchemaOnClassQuery
{
    #[Query(name: 'classLevelQuery')]
    public function resolveQuery(): string
    {
        return 'ok';
    }

    #[Mutation(name: 'classLevelMutation')]
    public function resolveMutation(): string
    {
        return 'ok';
    }
}
