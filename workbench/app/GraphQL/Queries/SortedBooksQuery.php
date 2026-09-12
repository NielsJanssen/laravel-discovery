<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sort;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Sortable;

class SortedBooksQuery
{
    #[Query(name: 'sortedBooks')]
    #[Sortable(['title', 'author'], defaultField: 'title', defaultDirection: 'desc')]
    public function resolve(Sort $sort): string
    {
        return ($sort->field ?? 'none') . ':' . $sort->direction;
    }
}
