<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

final class ParcelMutations
{
    public static ?TrackParcel $flattened = null;

    public static ?TrackParcel $nested = null;

    #[Mutation]
    public function trackParcel(#[AsArgs] TrackParcel $parcel): string
    {
        self::$flattened = $parcel;

        return $parcel->trackingCode;
    }

    #[Mutation]
    public function trackNested(TrackParcel $parcelInput): string
    {
        self::$nested = $parcelInput;

        return $parcelInput->trackingCode;
    }
}
