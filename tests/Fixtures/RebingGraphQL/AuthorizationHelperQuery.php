<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class AuthorizationHelperQuery
{
    #[Query]
    public function injectedHelper(Authorization $auth): string
    {
        return 'ok';
    }

    #[Query]
    #[Authorize]
    public function composedHelper(Authorization $auth): string
    {
        return 'ok';
    }
}
