<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class TierQuery
{
    #[Query]
    public function customer(): Customer
    {
        return new Customer('Gold');
    }

    #[Query]
    public function tierOf(Tier $tier): string
    {
        return $tier::class . '::' . $tier->name;
    }
}
