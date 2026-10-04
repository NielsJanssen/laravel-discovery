<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class AcmeLooseBoard
{
    public ?AcmeAdminNote $pinned = null;
}
