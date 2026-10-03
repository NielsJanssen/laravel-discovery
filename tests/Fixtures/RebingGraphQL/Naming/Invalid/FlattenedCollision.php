<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use Tests\Fixtures\RebingGraphQL\Naming\TrackParcel;

final class FlattenedCollision
{
    #[Mutation]
    public function track(string $tracking_code, #[AsArgs] TrackParcel $parcel): string
    {
        return $tracking_code . $parcel->trackingCode;
    }
}
