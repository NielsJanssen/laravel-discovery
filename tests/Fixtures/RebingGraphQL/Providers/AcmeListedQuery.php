<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeListedQuery
{
    #[Query]
    public function listed(): AcmeListed
    {
        return new AcmeListed();
    }
}
