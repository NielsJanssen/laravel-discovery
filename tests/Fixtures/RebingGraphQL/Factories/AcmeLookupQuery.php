<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeLookupQuery
{
    #[Query]
    public function lookup(): AcmeLookup
    {
        return new AcmeLookup();
    }
}
