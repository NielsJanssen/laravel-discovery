<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class UnregisteredReturnQuery
{
    #[Query]
    public function thing(): Unregistered
    {
        return new Unregistered();
    }
}
