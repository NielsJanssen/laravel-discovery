<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class AuthorizedUserQuery
{
    #[Query(name: 'authorizedUser')]
    public function resolve(
        #[Arg('id')]
        #[Authorize('view')]
        User $user,
    ): string {
        return $user->name;
    }
}
