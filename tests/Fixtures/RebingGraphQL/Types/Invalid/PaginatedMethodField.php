<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Pagination;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class PaginatedMethodField
{
    /** @return list<string> */
    #[Field(of: 'string')]
    #[Paginated]
    public function pages(Pagination $page): array
    {
        return [];
    }
}
