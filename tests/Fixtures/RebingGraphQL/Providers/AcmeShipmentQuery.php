<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeShipmentQuery
{
    #[Query]
    public function shipment(): AcmeShipment
    {
        return new AcmeShipment();
    }

    #[Query]
    public function maybeShipment(): ?AcmeShipment
    {
        return null;
    }

    /**
     * @return list<AcmeShipment>
     */
    #[Query(of: AcmeShipment::class)]
    public function shipments(): array
    {
        return [new AcmeShipment('S-1', 12), new AcmeShipment('S-2', 7)];
    }
}
