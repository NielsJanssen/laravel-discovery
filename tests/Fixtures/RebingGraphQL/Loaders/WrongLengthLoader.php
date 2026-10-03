<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchLoader;

final class WrongLengthLoader implements BatchLoader
{
    public static int $runs = 0;

    public function load(array $roots, array $options, array $args): array
    {
        self::$runs++;

        return [['only one']];
    }
}
