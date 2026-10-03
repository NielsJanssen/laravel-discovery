<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class IssueQueries
{
    #[Query]
    public function latest_issue(?int $page_size = null): Issue
    {
        return new Issue();
    }
}
