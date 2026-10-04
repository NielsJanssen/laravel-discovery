<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Size;

final class WarmSmallBench extends WarmExecution
{
    protected function size(): Size
    {
        return Size::Small;
    }
}
