<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class AuthorizedOptionalUserQuery
{
    #[Query(name: 'authorizedOptionalUser')]
    public function resolve(
        #[Arg('id')]
        #[Authorize('view')]
        ?User $user = null,
    ): ?string {
        return $user?->name;
    }
}
