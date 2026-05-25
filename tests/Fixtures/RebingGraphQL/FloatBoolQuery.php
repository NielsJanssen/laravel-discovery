<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class FloatBoolQuery
{
    #[Query(name: 'percent')]
    public function resolve(bool $enabled, float $threshold): float
    {
        return $enabled ? $threshold : 0.0;
    }
}
