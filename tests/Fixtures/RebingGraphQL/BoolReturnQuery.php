<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class BoolReturnQuery
{
    #[Query(name: 'isReady')]
    public function resolve(): bool
    {
        return true;
    }
}
