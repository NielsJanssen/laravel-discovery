<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class MixedReturnQuery
{
    #[Query]
    public function whatever(): mixed
    {
        return 'x';
    }
}
