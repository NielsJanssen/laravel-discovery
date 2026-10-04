<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;

#[Schema('admin')]
final class AcmeAdminQueries
{
    #[Query]
    public function report(AcmeReportFilter $filter): AcmeReport
    {
        return new AcmeReport('Q3', $filter->severity, [new AcmeReportLine('admin line')]);
    }

    #[Query]
    public function adminLine(): AcmeReportLine
    {
        return new AcmeReportLine('admin line');
    }
}
