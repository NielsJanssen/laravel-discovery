<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class AbilityOnMethodQuery
{
    #[Query]
    #[Authorize('view')]
    public function abilityOnMethod(): string
    {
        return 'ok';
    }
}
