<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeWarehouseQuery
{
    /**
     * @return array<string, mixed>
     */
    #[Query(type: 'AcmeWarehouse')]
    public function warehouse(): array
    {
        return ['name' => 'North', 'capacity' => 40];
    }
}
