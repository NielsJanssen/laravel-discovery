<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use NielsJanssen\Laravel\Discovery\Schedule\ScheduleDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Schedule\BasicScheduledTask;
use Tests\Fixtures\Schedule\ClosureConfiguredTask;
use Tests\Fixtures\Schedule\FrequencyEnumTask;
use Tests\Fixtures\Schedule\HourlyTask;
use Tests\Fixtures\Schedule\MultipleScheduledTask;
use Tests\Fixtures\Schedule\NamedTask;
use Tests\Fixtures\Schedule\UnattributedTask;

function discoverSchedule(string ...$classes): Schedule
{
    $discovery = app(ScheduleDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Schedule',
        path: dirname(__DIR__, 2) . '/Fixtures/Schedule',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    $discovery->apply();

    return app(Schedule::class);
}

beforeEach(function () {
    app()->forgetInstance(Schedule::class);
    app()->forgetInstance(ScheduleDiscovery::class);
});

it('registers a task with an every string', function () {
    $schedule = discoverSchedule(BasicScheduledTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->expression)->toBe('*/15 * * * *');
});

it('registers an hourly task', function () {
    $schedule = discoverSchedule(HourlyTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->expression)->toBe('0 * * * *');
});

it('registers a task configured via closure', function () {
    $schedule = discoverSchedule(ClosureConfiguredTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->withoutOverlapping)->toBeTrue();
});

it('registers one event per Scheduled attribute when a method has multiple', function () {
    $schedule = discoverSchedule(MultipleScheduledTask::class);

    expect($schedule->events())->toHaveCount(2);
});

it('appends a unique index to auto-generated names when a method has multiple Scheduled attributes', function () {
    $schedule = discoverSchedule(MultipleScheduledTask::class);
    $names    = array_map(fn($e) => $e->description, $schedule->events());

    expect($names)->toContain(MultipleScheduledTask::class . '@run#0');
    expect($names)->toContain(MultipleScheduledTask::class . '@run#1');
});

it('skips methods without the Scheduled attribute', function () {
    $schedule = discoverSchedule(UnattributedTask::class);

    expect($schedule->events())->toHaveCount(0);
});

it('auto-generates a name from the class and method', function () {
    $schedule = discoverSchedule(BasicScheduledTask::class);

    expect($schedule->events()[0]->description)->toContain('BasicScheduledTask@run');
});

it('uses the explicit name when provided', function () {
    $schedule = discoverSchedule(NamedTask::class);

    expect($schedule->events()[0]->description)->toBe('my-named-task');
});

it('accepts a Frequency enum as the every value', function () {
    $schedule = discoverSchedule(FrequencyEnumTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->expression)->toBe('0 0 * * *');
});
