<?php

declare(strict_types=1);

namespace Workbench\App\Test;

class RandomNumberGenerator
{
    public function generate(int $min = 0, int $max = 99999): int
    {
        return random_int($min, $max);
    }
}
