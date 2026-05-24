<?php

declare(strict_types=1);

namespace Tests\Feature;

use InvalidArgumentException;
use NielsJanssen\Laravel\Discovery\Schedule\Interval;

it('converts 1 minute to a wildcard minute field', function () {
    expect((new Interval(minutes: 1))->toCronExpression())->toBe('* * * * *');
});

it('converts N minutes to a step minute field', function () {
    expect((new Interval(minutes: 15))->toCronExpression())->toBe('*/15 * * * *');
    expect((new Interval(minutes: 30))->toCronExpression())->toBe('*/30 * * * *');
});

it('converts 1 hour to a wildcard hour field with zeroed minute', function () {
    expect((new Interval(hours: 1))->toCronExpression())->toBe('0 * * * *');
});

it('converts N hours to a step hour field', function () {
    expect((new Interval(hours: 2))->toCronExpression())->toBe('0 */2 * * *');
    expect((new Interval(hours: 6))->toCronExpression())->toBe('0 */6 * * *');
});

it('converts 1 day to a zeroed time with wildcard day field', function () {
    expect((new Interval(days: 1))->toCronExpression())->toBe('0 0 * * *');
});

it('converts N days to a step day field', function () {
    expect((new Interval(days: 3))->toCronExpression())->toBe('0 0 */3 * *');
});

it('converts weeks to a step day field using 7-day multiples', function () {
    expect((new Interval(weeks: 1))->toCronExpression())->toBe('0 0 */7 * *');
    expect((new Interval(weeks: 2))->toCronExpression())->toBe('0 0 */14 * *');
});

it('converts 1 month to a wildcard month field on the first of the month', function () {
    expect((new Interval(months: 1))->toCronExpression())->toBe('0 0 1 * *');
});

it('converts N months to a step month field', function () {
    expect((new Interval(months: 3))->toCronExpression())->toBe('0 0 1 */3 *');
    expect((new Interval(months: 6))->toCronExpression())->toBe('0 0 1 */6 *');
});

it('converts years to January 1st annually', function () {
    expect((new Interval(years: 1))->toCronExpression())->toBe('0 0 1 1 *');
});

it('excludes seconds from the cron expression', function () {
    expect((new Interval(seconds: 30))->toCronExpression())->toBe('* * * * *');
});

it('anchors smaller fields when a larger unit is set', function () {
    expect((new Interval(hours: 2, minutes: 30))->toCronExpression())->toBe('30 */2 * * *');
    expect((new Interval(days: 1, hours: 6))->toCronExpression())->toBe('0 */6 * * *');
});

it('throws when constructed with no interval values', function () {
    new Interval();
})->throws(InvalidArgumentException::class, 'At least one interval value must be provided.');
