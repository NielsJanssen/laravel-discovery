<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ExplicitOverMapperQuery
{
    #[Query(type: 'String')]
    public function label(#[Arg(type: 'String')] Money $amount): Money
    {
        return $amount;
    }

    #[Query(of: 'String')]
    public function labels(): Money
    {
        return new Money(0, 'EUR');
    }

    #[Query(type: ExplicitOverMapper::class)]
    public function explicit(): ExplicitOverMapper
    {
        return new ExplicitOverMapper();
    }
}
