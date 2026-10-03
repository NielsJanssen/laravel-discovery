<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class DuplicateOperations
{
    #[Query]
    public function latest_issue(): string
    {
        return 'snake';
    }

    #[Query]
    public function latestIssue(): string
    {
        return 'camel';
    }
}
