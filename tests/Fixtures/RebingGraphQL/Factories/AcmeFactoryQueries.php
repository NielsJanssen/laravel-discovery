<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeFactoryQueries
{
    #[Query]
    public function company(): AcmeCompany
    {
        return new AcmeCompany();
    }

    #[Query]
    public function lookup(): AcmeLookup
    {
        return new AcmeLookup();
    }
}
