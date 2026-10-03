<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

#[Authorize('viewAny')]
class AbilityOnClassQuery
{
    #[Query]
    public function abilityOnClass(): string
    {
        return 'ok';
    }
}
