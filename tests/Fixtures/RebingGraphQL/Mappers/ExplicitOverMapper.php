<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class ExplicitOverMapper
{
    #[Field(type: 'String')]
    public int $id = 0;

    #[Field(of: 'Int')]
    public Money $instalments;

    #[Field(type: 'Int')]
    public function cents(#[Arg(type: 'Int')] string $id): Money
    {
        return new Money((int) $id, 'EUR');
    }
}
