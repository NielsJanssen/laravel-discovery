<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class HydratedCursorQuery
{
    #[Query(name: 'hydratedCursor')]
    #[CursorArgs]
    public function resolve(PlainCursor $cursor): string
    {
        return "cursor {$cursor->cursor}";
    }
}
