<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ContainerMoneyQuery
{
    #[Query]
    public function charge(Money $amount, #[Arg] Money $other): string
    {
        return $other->format();
    }
}
