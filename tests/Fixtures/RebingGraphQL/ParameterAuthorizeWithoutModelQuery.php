<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ParameterAuthorizeWithoutModelQuery
{
    #[Query]
    public function unboundAuthorize(#[Authorize('view')] string $name): string
    {
        return $name;
    }
}
