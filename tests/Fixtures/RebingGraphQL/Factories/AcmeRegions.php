<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

final class AcmeRegions
{
    public function label(string $code): string
    {
        return "Region $code";
    }
}
