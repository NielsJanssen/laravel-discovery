<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Enum;

#[Enum(schema: 'admin')]
enum AcmeAuditLevel
{
    case Full;
}
