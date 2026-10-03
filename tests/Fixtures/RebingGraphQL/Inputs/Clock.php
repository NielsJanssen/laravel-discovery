<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

final class Clock
{
    public function now(): string
    {
        return 'noon';
    }
}
