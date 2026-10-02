<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class MethodFieldWithRules
{
    #[Field]
    public function excerpt(#[Arg(rules: ['min:1'])] int $length): string
    {
        return str_repeat('x', $length);
    }
}
