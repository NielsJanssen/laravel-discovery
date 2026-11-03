<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class TraitDecoratedQueries
{
    use ForAdmins;
    use ForReports;
    use LooksLikeAType;

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
