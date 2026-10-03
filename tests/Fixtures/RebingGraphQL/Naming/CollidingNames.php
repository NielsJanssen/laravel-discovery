<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class CollidingNames
{
    #[Query]
    public function lookup(string $userId, string $user_id): string
    {
        return $userId . $user_id;
    }
}
