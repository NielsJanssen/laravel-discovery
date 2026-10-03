<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\Providers\AcmeShipment;

#[TypeExtension(AcmeShipment::class)]
final class AcmeShipmentNotes
{
    #[Field]
    public function note(#[Root] AcmeShipment $shipment): string
    {
        return "{$shipment->reference} weighs {$shipment->weight}";
    }
}
