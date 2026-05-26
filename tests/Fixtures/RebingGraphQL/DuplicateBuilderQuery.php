<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class DuplicateBuilderQuery
{
    #[Query(type: 'Book', name: 'duplicateBuilders')]
    #[Paginated]
    #[WrapInListBuilder]
    public function resolve(): mixed
    {
        return null;
    }
}
