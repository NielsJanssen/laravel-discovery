<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class PriceListQuery
{
    /** @return list<Money|null> */
    #[Query]
    public function prices(): array
    {
        return [new Money(100, 'EUR'), null];
    }
}
