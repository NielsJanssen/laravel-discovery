<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

final readonly class DiscoveredSchedule
{
    public function __construct(
        public string $className,
        public string $methodName,
    ) {}
}
