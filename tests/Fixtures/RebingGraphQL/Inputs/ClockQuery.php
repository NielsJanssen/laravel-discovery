<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ClockQuery
{
    #[Query]
    public function time(Clock $clock): string
    {
        return $clock->now();
    }
}
