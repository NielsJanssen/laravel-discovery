<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ListReturnQuery
{
    /**
     * @return list<string>|Omitted
     */
    #[Query(of: 'string')]
    public function titles(): array|Omitted
    {
        return Omitted::Value;
    }
}
