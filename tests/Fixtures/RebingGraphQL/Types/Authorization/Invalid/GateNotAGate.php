<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\Member;

#[Type]
final class GateNotAGate
{
    #[Authorize(gate: Member::class)]
    public string $name = 'x';
}
