<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class PlainIdQuery
{
    #[Query]
    public function find(int $id): int
    {
        return $id;
    }
}
