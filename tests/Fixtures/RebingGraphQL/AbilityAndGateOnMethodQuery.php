<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class AbilityAndGateOnMethodQuery
{
    #[Query]
    #[Authorize('view', gate: AlwaysAllowGate::class)]
    public function abilityAndGate(): string
    {
        return 'ok';
    }
}
