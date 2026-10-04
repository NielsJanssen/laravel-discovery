<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

#[Schema('admin')]
final class AcmeLedgerQuery
{
    /**
     * @return array{entry: string}
     */
    #[Query(type: 'AcmeLedger')]
    public function ledger(): array
    {
        return ['entry' => 'E1'];
    }
}
