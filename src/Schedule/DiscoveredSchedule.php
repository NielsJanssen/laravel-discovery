<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

final class DiscoveredSchedule
{
    public function __construct(
        public readonly string $className,
        public readonly string $methodName,
        public readonly string $name,
        public readonly ?Scheduled $schedule,
        public readonly int $attributeIndex,
    ) {}
}
