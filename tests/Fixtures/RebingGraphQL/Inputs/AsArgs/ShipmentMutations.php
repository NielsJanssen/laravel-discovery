<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ShipmentMutations
{
    public static ?Shipment $received = null;

    #[Mutation]
    public function ship(#[AsArgs] Shipment $shipment): int
    {
        self::$received = $shipment;

        return $shipment->parcel->weight;
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
