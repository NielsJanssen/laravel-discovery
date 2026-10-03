<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class MixedReturnQuery
{
    #[Query]
    public function anything(): mixed
    {
        return null;
    }
}
