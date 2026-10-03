<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ScalarActions
{
    #[Query]
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }

    #[Query]
    public function maybeGreet(): ?string
    {
        return null;
    }

    #[Query]
    public function isReady(): bool
    {
        return true;
    }

    #[Query]
    public function percent(bool $enabled, float $threshold): float
    {
        return $enabled ? $threshold : 0.0;
    }

    #[Query]
    public function doNothing(): void {}

    #[Query]
    public function add(int $a = 0, int $b = 0): int
    {
        return $a + $b;
    }
}
