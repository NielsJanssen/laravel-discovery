<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmePublicQueries
{
    #[Query]
    public function line(): AcmeReportLine
    {
        return new AcmeReportLine('public line');
    }
}
