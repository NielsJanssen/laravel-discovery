<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sort;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sortable;

class SortableQueries
{
    #[Query(name: 'sortedSeparate')]
    #[Sortable(['title', 'author'])]
    public function separate(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }

    #[Query(name: 'sortedUnified')]
    #[Sortable(['title'], unified: true)]
    public function unified(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }

    #[Query(name: 'sortedSeparateDefault')]
    #[Sortable(['title', 'author'], defaultField: 'author')]
    public function separateDefault(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }

    #[Query(name: 'sortedUnifiedDefault')]
    #[Sortable(['title', 'author'], unified: true, defaultDirection: 'desc', defaultField: 'title')]
    public function unifiedDefault(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }
}
