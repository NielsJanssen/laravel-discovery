<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class TypeAndOfField
{
    /** @var list<string> */
    #[Field(type: 'String', of: 'string')]
    public array $tags = [];
}
