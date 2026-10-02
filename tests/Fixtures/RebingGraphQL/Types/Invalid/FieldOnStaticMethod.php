<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class FieldOnStaticMethod
{
    #[Field]
    public static function shared(): string
    {
        return 'shared';
    }
}
