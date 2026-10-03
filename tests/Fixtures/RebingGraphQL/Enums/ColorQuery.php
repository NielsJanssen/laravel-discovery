<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ColorQuery
{
    #[Query]
    public function mix(Color $color): Color
    {
        return $color === Color::Red ? Color::Green : Color::Red;
    }
}
