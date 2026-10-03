<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeYieldedQuery
{
    #[Query]
    public function yielded(): AcmeYielded
    {
        return new AcmeYielded();
    }
}
