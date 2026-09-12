<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sort;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sortable;

class SortableUnknownDefaultQuery
{
    #[Query(name: 'sortedUnknownDefault')]
    #[Sortable(['title'], defaultField: 'published_at')]
    public function resolve(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }
}
