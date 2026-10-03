<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchLoader;

final class NullLoader implements BatchLoader
{
    public function load(array $roots, array $options, array $args): array
    {
        return array_fill(0, count($roots), null);
    }
}
