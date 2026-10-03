<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

final class AcmeShipment
{
    public function __construct(
        public string $reference = 'S-1',
        public int $weight = 12,
    ) {}
}
