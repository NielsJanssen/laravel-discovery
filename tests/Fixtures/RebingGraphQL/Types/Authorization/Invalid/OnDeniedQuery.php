<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Denied;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class OnDeniedQuery
{
    #[Query]
    #[Authorize(onDenied: Denied::Null)]
    public function secret(): string
    {
        return 'secret';
    }
}
