<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Ignore;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class FieldAndIgnoreMethod
{
    #[Field, Ignore]
    public function label(): string
    {
        return 'label';
    }
}
