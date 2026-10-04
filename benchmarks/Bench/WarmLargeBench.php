<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Size;

final class WarmLargeBench extends WarmExecution
{
    protected function size(): Size
    {
        return Size::Large;
    }
}
