<?php

declare(strict_types=1);

namespace Benchmarks\Support;

enum Setup: string
{
    case Rebing = 'rebing';
    case RebingEager = 'rebing-eager';
    case Uncached = 'uncached';
    case Cached = 'cached';

    public function usesDiscovery(): bool
    {
        return $this === self::Uncached || $this === self::Cached;
    }

    /** @return list<self> */
    public static function compared(): array
    {
        return [self::Rebing, self::Uncached, self::Cached];
    }
}
