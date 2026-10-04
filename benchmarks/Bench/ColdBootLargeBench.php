<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Size;

final class ColdBootLargeBench extends ColdBoot
{
    protected function size(): Size
    {
        return Size::Large;
    }
}
