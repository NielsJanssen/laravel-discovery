<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class AcmeListed
{
    public string $reference = 'L-1';
}
