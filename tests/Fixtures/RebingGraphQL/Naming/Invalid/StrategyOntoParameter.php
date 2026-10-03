<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class StrategyOntoParameter
{
    #[Query]
    public function lookup(string $userId, #[Arg('uid')] string $user_id): string
    {
        return $userId . $user_id;
    }
}
