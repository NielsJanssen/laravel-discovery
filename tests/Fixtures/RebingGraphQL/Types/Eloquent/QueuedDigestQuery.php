<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class QueuedDigestQuery
{
    #[Query]
    public function digest(): QueuedDigest
    {
        return new QueuedDigest();
    }
}
