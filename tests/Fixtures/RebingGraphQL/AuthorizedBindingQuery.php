<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class AuthorizedBindingQuery
{
    #[Query]
    public function authorizedUser(#[Arg('id')] #[Authorize('view')] User $user): string
    {
        return $user->name;
    }

    #[Query]
    public function authorizedWithMessage(
        #[Arg('id')]
        #[Authorize('view', message: 'Not your user')]
        User $user,
    ): string {
        return $user->name;
    }

    #[Query]
    public function twiceAuthorized(
        #[Arg('id')]
        #[Authorize('view')]
        #[Authorize('update')]
        User $user,
    ): string {
        return $user->name;
    }

    #[Query]
    public function authorizedOptionalUser(#[Authorize('view')] ?User $user = null): string
    {
        return $user?->name ?? 'none';
    }
}
