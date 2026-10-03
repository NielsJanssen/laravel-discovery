<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeDepotQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    #[Query(of: 'AcmeDepot')]
    public function depots(): array
    {
        return [['code' => 'D1'], ['code' => 'D2']];
    }
}
