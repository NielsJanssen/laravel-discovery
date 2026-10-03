<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class FilterQuery
{
    #[Query]
    public function search(FilterInput $filter = new FilterInput('all')): string
    {
        return $filter->term ?? 'none';
    }
}
