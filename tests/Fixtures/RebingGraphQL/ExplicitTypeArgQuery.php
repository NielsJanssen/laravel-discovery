<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ExplicitTypeArgQuery
{
    #[Query(type: 'Book')]
    public function resolve(
        #[Arg(type: 'CustomFilter')]
        string $filter,
    ): array {
        return [];
    }
}
