<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Interval;

it('maps Every cases to the correct Interval', function (Every $case, Interval $expected) {
    expect($case->asInterval())->toEqual($expected);
})->with([
    'Second'         => [Every::Second,         new Interval(seconds: 1)],
    'TwoSeconds'     => [Every::TwoSeconds,      new Interval(seconds: 2)],
    'FiveSeconds'    => [Every::FiveSeconds,     new Interval(seconds: 5)],
    'TenSeconds'     => [Every::TenSeconds,      new Interval(seconds: 10)],
    'FifteenSeconds' => [Every::FifteenSeconds,  new Interval(seconds: 15)],
    'TwentySeconds'  => [Every::TwentySeconds,   new Interval(seconds: 20)],
    'ThirtySeconds'  => [Every::ThirtySeconds,   new Interval(seconds: 30)],
    'Minute'         => [Every::Minute,          new Interval(minutes: 1)],
    'TwoMinutes'     => [Every::TwoMinutes,      new Interval(minutes: 2)],
    'ThreeMinutes'   => [Every::ThreeMinutes,    new Interval(minutes: 3)],
    'FourMinutes'    => [Every::FourMinutes,     new Interval(minutes: 4)],
    'FiveMinutes'    => [Every::FiveMinutes,     new Interval(minutes: 5)],
    'TenMinutes'     => [Every::TenMinutes,      new Interval(minutes: 10)],
    'FifteenMinutes' => [Every::FifteenMinutes,  new Interval(minutes: 15)],
    'ThirtyMinutes'  => [Every::ThirtyMinutes,   new Interval(minutes: 30)],
    'Hour'           => [Every::Hour,            new Interval(hours: 1)],
    'TwoHours'       => [Every::TwoHours,        new Interval(hours: 2)],
    'ThreeHours'     => [Every::ThreeHours,      new Interval(hours: 3)],
    'FourHours'      => [Every::FourHours,       new Interval(hours: 4)],
    'SixHours'       => [Every::SixHours,        new Interval(hours: 6)],
    'Day'            => [Every::Day,             new Interval(days: 1)],
    'Week'           => [Every::Week,            new Interval(weeks: 1)],
    'Month'          => [Every::Month,           new Interval(months: 1)],
    'Quarter'        => [Every::Quarter,         new Interval(months: 3)],
    'Year'           => [Every::Year,            new Interval(years: 1)],
]);
