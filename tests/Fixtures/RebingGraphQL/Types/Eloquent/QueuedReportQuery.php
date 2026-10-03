<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class QueuedReportQuery
{
    #[Query]
    public function report(): QueuedReport
    {
        return new QueuedReport();
    }
}
