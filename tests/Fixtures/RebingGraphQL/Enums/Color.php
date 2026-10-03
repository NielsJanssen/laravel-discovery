<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Enum;

#[Enum(name: 'Colour', description: 'Named explicitly')]
enum Color: int
{
    case Red = 1;
    case Green = 2;
}
