<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class GateNotAGateQuery
{
    #[Query]
    #[Authorize(gate: ContainerService::class)]
    public function notAGate(): string
    {
        return 'ok';
    }
}
