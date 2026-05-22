<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

enum Frequency: string
{
    case EveryMinute = 'minute';
    case EveryTwoMinutes = '2 minutes';
    case EveryThreeMinutes = '3 minutes';
    case EveryFourMinutes = '4 minutes';
    case EveryFiveMinutes = '5 minutes';
    case EveryTenMinutes = '10 minutes';
    case EveryFifteenMinutes = '15 minutes';
    case EveryThirtyMinutes = '30 minutes';
    case Hourly = 'hour';
    case EveryTwoHours = '2 hours';
    case EveryThreeHours = '3 hours';
    case EveryFourHours = '4 hours';
    case EverySixHours = '6 hours';
    case Daily = 'day';
    case Weekly = 'week';
    case Monthly = 'month';
    case Quarterly = 'quarter';
    case Yearly = 'year';
}
