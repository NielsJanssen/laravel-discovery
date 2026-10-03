<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Decorators;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class UppercaseOnInt
{
    #[Uppercase]
    public int $count = 1;
}
