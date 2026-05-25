<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Timezone implements ScheduleDecorator
{
    public function __construct(
        public readonly string $timezone,
    ) {}

    public function decorate(Scheduled $scheduled): Scheduled
    {
        $scheduled->timezone = $this->timezone;

        return $scheduled;
    }
}
