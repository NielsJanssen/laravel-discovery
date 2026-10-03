<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Types\Authorization\StaffOnlyGate;

#[Type]
final class AbilityAndGate
{
    #[Authorize('view', gate: StaffOnlyGate::class)]
    public string $name = 'x';
}
