<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Fields;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class RowsQuery
{
    /**
     * @return list<Row>
     */
    #[Query(of: Row::class)]
    public function rows(int $count): array
    {
        return array_map(static fn(int $id): Row => new Row($id), range(1, $count));
    }
}
