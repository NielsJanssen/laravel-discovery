<?php

declare(strict_types=1);

namespace Benchmarks\Bench;

use Benchmarks\Support\Size;

final class ScenarioSmallBench extends Scenario
{
    protected function size(): Size
    {
        return Size::Small;
    }
}
