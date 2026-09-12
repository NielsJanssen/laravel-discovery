<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class GuardedReportQuery
{
    #[Query(name: 'guardedReport')]
    public function resolve(Authorization $auth): string
    {
        $auth->authorize('view-report');

        return 'quarterly';
    }
}
