<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Size;

final class HttpSmallBench extends Http
{
    protected function size(): Size
    {
        return Size::Small;
    }
}
