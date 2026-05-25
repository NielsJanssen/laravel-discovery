<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class BareDeprecatedQuery
{
    #[Query(name: 'bareDeprecated')]
    #[\Deprecated]
    public function resolve(): string
    {
        return 'ok';
    }
}
