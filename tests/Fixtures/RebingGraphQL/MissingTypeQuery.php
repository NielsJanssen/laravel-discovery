<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class MissingTypeQuery
{
    #[Query(name: 'books')]
    public function resolve(): array
    {
        return [];
    }
}
