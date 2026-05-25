<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class SinceOnlyDeprecatedQuery
{
    #[Query(name: 'sinceOnly')]
    #[\Deprecated(since: '3.0.0')]
    public function resolve(): string
    {
        return 'ok';
    }
}
