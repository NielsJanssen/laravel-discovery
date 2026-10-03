<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ClashingMoodsQuery
{
    #[Query]
    public function both(Mood $here, Clash\Mood $there): string
    {
        return $here->name . $there->name;
    }
}
