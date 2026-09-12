<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class ParameterAuthorizeWithoutAbilityQuery
{
    #[Query]
    public function abilitylessAuthorize(#[Arg('id')] #[Authorize] User $user): string
    {
        return $user->name;
    }
}
