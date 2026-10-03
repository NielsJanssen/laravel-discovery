<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class TypedReturnQuery
{
    #[Query(type: 'String')]
    public function title(): string|Omitted
    {
        return Omitted::Value;
    }
}
