<?php

declare(strict_types=1);

namespace Benchmarks\Support;

final readonly class Target
{
    public function __construct(
        public Setup $setup,
        public Size $size,
    ) {}
}
