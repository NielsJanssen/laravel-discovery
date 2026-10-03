<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;

final class TrailingUnderscore implements NamingStrategy
{
    public function name(string $phpName): string
    {
        return $phpName . '_';
    }
}
