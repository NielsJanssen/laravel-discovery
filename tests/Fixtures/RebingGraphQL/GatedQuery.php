<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

#[Authorize(gate: AlwaysAllowGate::class)]
class GatedQuery
{
    #[Query(name: 'gatedDeny')]
    #[Authorize(gate: AlwaysDenyGate::class, message: 'denied by gate')]
    public function denied(): string
    {
        return 'unreachable';
    }

    #[Query(name: 'gatedAllow')]
    public function allowed(): string
    {
        return 'ok';
    }
}
