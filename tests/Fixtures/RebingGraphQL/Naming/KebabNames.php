<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use Illuminate\Support\Str;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\NamingStrategy;

final class KebabNames implements NamingStrategy
{
    public function name(string $phpName): string
    {
        return Str::kebab($phpName);
    }
}
