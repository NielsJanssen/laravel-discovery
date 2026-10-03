<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input(naming: self::class)]
final class LoudInput
{
    public string $title = '';
}
