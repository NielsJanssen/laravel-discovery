<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class SecretQuery
{
    #[Query(name: 'secret')]
    #[Authorize]
    public function resolve(): string
    {
        return 'top-secret';
    }
}
