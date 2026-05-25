<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class WithoutOverlapping implements ScheduleDecorator
{
    public function __construct(
        public readonly int $expiresAt = 1440,
        public readonly bool $releaseOnTerminationSignals = true,
    ) {}

    public function decorate(Scheduled $scheduled): Scheduled
    {
        $scheduled->withoutOverlapping = true;
        $scheduled->withoutOverlappingExpiry = $this->expiresAt;
        $scheduled->releaseOnTerminationSignals = $this->releaseOnTerminationSignals;

        return $scheduled;
    }
}
