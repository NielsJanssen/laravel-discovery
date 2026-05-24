<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

interface ScheduleDecorator
{
    public function decorate(Scheduled $scheduled): Scheduled;
}
