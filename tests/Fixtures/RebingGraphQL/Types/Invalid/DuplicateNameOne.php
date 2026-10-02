<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(name: 'Duplicate')]
final class DuplicateNameOne
{
    public string $label = 'one';
}
