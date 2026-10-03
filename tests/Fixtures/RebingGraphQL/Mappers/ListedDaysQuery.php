<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use Carbon\CarbonImmutable;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ListedDaysQuery
{
    #[Query(list: true)]
    public function listedDays(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }
}
