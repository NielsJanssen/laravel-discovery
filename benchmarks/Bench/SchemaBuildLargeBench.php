<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Size;

final class SchemaBuildLargeBench extends SchemaBuild
{
    protected function size(): Size
    {
        return Size::Large;
    }
}
