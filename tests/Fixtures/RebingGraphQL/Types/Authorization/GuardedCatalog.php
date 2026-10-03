<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
#[Authorize]
final class GuardedCatalog
{
    public string $title = 'Spring catalog';

    #[Query]
    public function catalog(): self
    {
        return new self();
    }
}
