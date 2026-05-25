<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

class BetweenTime
{
    public function __construct(
        public string $startTime,
        public string $endTime,
    ) {}
}
