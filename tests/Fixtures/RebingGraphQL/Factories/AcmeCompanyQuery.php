<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeCompanyQuery
{
    #[Query]
    public function company(): AcmeCompany
    {
        return new AcmeCompany();
    }
}
