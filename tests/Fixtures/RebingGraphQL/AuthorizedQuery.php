<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class AuthorizedQuery
{
    #[Query(name: 'mustBeLoggedIn')]
    #[Authorize(message: 'Authentication required')]
    public function resolve(): string
    {
        return 'ok';
    }
}
