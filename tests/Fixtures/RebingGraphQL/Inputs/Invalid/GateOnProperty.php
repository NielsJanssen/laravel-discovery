<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Tests\Fixtures\RebingGraphQL\AlwaysAllowGate;
use Workbench\App\Models\User;

#[Input]
final class GateOnProperty
{
    #[Authorize('view', gate: AlwaysAllowGate::class)]
    public User $owner;
}
