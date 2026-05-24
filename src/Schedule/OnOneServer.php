<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class OnOneServer implements ScheduleDecorator
{
    public function decorate(Scheduled $scheduled): Scheduled
    {
        $scheduled->onOneServer = true;

        return $scheduled;
    }
}
