<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sort;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sortable;

class SortableDefaultFieldQuery
{
    #[Query(name: 'sortedSeparateDefault')]
    #[Sortable(['title', 'author'], defaultField: 'author')]
    public function separate(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }

    #[Query(name: 'sortedUnifiedDefault')]
    #[Sortable(['title', 'author'], unified: true, defaultDirection: 'desc', defaultField: 'title')]
    public function unified(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }
}
