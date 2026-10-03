<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(naming: 'loud')]
final class LoudType
{
    public string $title = '';
}
