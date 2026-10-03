<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class CollidesWithProvider
{
    #[Query(type: 'String')]
    #[Paginated]
    public function pages(#[AsArgs] PageCursor $cursor, Pagination $pagination): array
    {
        return [];
    }
}
