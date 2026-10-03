<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Denied;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

final class OnDeniedParameterQuery
{
    #[Query]
    public function userName(#[Arg('id')] #[Authorize('view', onDenied: Denied::Error)] User $user): string
    {
        return $user->name;
    }
}
