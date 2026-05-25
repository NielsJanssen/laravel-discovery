<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Schedule;

enum Every
{
    case Second;
    case TwoSeconds;
    case FiveSeconds;
    case TenSeconds;
    case FifteenSeconds;
    case TwentySeconds;
    case ThirtySeconds;
    case Minute;
    case TwoMinutes;
    case ThreeMinutes;
    case FourMinutes;
    case FiveMinutes;
    case TenMinutes;
    case FifteenMinutes;
    case ThirtyMinutes;
    case Hour;
    case TwoHours;
    case ThreeHours;
    case FourHours;
    case SixHours;
    case Day;
    case Week;
    case Month;
    case Quarter;
    case Year;

    public function asInterval(): Interval
    {
        return match ($this) {
            self::Second => new Interval(seconds: 1),
            self::TwoSeconds => new Interval(seconds: 2),
            self::FiveSeconds => new Interval(seconds: 5),
            self::TenSeconds => new Interval(seconds: 10),
            self::FifteenSeconds => new Interval(seconds: 15),
            self::TwentySeconds => new Interval(seconds: 20),
            self::ThirtySeconds => new Interval(seconds: 30),
            self::Minute => new Interval(minutes: 1),
            self::TwoMinutes => new Interval(minutes: 2),
            self::ThreeMinutes => new Interval(minutes: 3),
            self::FourMinutes => new Interval(minutes: 4),
            self::FiveMinutes => new Interval(minutes: 5),
            self::TenMinutes => new Interval(minutes: 10),
            self::FifteenMinutes => new Interval(minutes: 15),
            self::ThirtyMinutes => new Interval(minutes: 30),
            self::Hour => new Interval(hours: 1),
            self::TwoHours => new Interval(hours: 2),
            self::ThreeHours => new Interval(hours: 3),
            self::FourHours => new Interval(hours: 4),
            self::SixHours => new Interval(hours: 6),
            self::Day => new Interval(days: 1),
            self::Week => new Interval(weeks: 1),
            self::Month => new Interval(months: 1),
            self::Quarter => new Interval(months: 3),
            self::Year => new Interval(years: 1),
        };
    }
}
