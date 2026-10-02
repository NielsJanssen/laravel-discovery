<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Fields;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(name: 'Renamed', description: 'Named explicitly')]
final class NamedType
{
    public string $label = 'label';
}
