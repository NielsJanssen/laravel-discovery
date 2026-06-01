<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

enum ScheduleTarget
{
    case Method;
    case Command;
    case Job;
}
