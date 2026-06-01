<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use NielsJanssen\Laravel\Discovery\Schedule\ScheduleDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Schedule\BasicScheduledTask;
use Tests\Fixtures\Schedule\BetweenTask;
use Tests\Fixtures\Schedule\ClassDecoratorTask;
use Tests\Fixtures\Schedule\ClassScheduledNonCommand;
use Tests\Fixtures\Schedule\ClosureConfiguredTask;
use Tests\Fixtures\Schedule\ClosureScheduledJob;
use Tests\Fixtures\Schedule\CronExpressionTask;
use Tests\Fixtures\Schedule\FrequencyEnumTask;
use Tests\Fixtures\Schedule\HourlyTask;
use Tests\Fixtures\Schedule\InlineModifiersTask;
use Tests\Fixtures\Schedule\MultipleScheduledCommand;
use Tests\Fixtures\Schedule\MultipleScheduledJob;
use Tests\Fixtures\Schedule\MultipleScheduledTask;
use Tests\Fixtures\Schedule\NamedTask;
use Tests\Fixtures\Schedule\OnOneServerTask;
use Tests\Fixtures\Schedule\ParameterizedCallbackTask;
use Tests\Fixtures\Schedule\ParameterizedScheduledCommand;
use Tests\Fixtures\Schedule\ProcessOrdersJob;
use Tests\Fixtures\Schedule\ReleaseOnTerminationTask;
use Tests\Fixtures\Schedule\ScheduledCommand;
use Tests\Fixtures\Schedule\TimezoneTask;
use Tests\Fixtures\Schedule\UnattributedTask;
use Tests\Fixtures\Schedule\UnlessBetweenTask;
use Tests\Fixtures\Schedule\WithoutOverlappingExpiryTask;
use Tests\Fixtures\Schedule\WithoutOverlappingTask;

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

it('registers a task with a raw Cron expression', function () {
    $schedule = discoverSchedule(CronExpressionTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->expression)->toBe('30 6 * * 1');
});

it('registers a task with a between time window', function () {
    $schedule = discoverSchedule(BetweenTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->expression)->toBe('0 * * * *');
});

it('registers a task with an unless-between time window', function () {
    $schedule = discoverSchedule(UnlessBetweenTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->expression)->toBe('0 * * * *');
});

it('registers a task with withoutOverlapping', function () {
    $schedule = discoverSchedule(WithoutOverlappingTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->withoutOverlapping)->toBeTrue();
});

it('registers a task with onOneServer', function () {
    $schedule = discoverSchedule(OnOneServerTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->onOneServer)->toBeTrue();
});

it('applies a method-level Timezone decorator', function () {
    $schedule = discoverSchedule(TimezoneTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->timezone)->toBe('Europe/Amsterdam');
});

it('applies withoutOverlapping with a custom expiry', function () {
    $schedule = discoverSchedule(WithoutOverlappingExpiryTask::class);

    expect($schedule->events())->toHaveCount(1);
    expect($schedule->events()[0]->withoutOverlapping)->toBeTrue();
    expect($schedule->events()[0]->expiresAt)->toBe(60);
});

it('applies inline withoutOverlapping, onOneServer and timezone on #[Scheduled]', function () {
    $schedule = discoverSchedule(InlineModifiersTask::class);

    $event = $schedule->events()[0];
    expect($event->withoutOverlapping)->toBeTrue();
    expect($event->onOneServer)->toBeTrue();
    expect($event->timezone)->toBe('UTC');
});

it('passes releaseOnTerminationSignals through the WithoutOverlapping decorator', function () {
    $schedule = discoverSchedule(ReleaseOnTerminationTask::class);

    $event = $schedule->events()[0];
    expect($event->withoutOverlapping)->toBeTrue();
    expect($event->expiresAt)->toBe(60);
    expect($event->releaseOnTerminationSignals)->toBeFalse();
});

it('applies class-level decorators to every method', function () {
    $schedule = discoverSchedule(ClassDecoratorTask::class);

    expect($schedule->events())->toHaveCount(2);

    foreach ($schedule->events() as $event) {
        expect($event->timezone)->toBe('Europe/Amsterdam');
        expect($event->withoutOverlapping)->toBeTrue();
        expect($event->expiresAt)->toBe(60);
        expect($event->onOneServer)->toBeTrue();
    }
});

it('schedules a traditional Laravel command via a class-level Scheduled attribute', function () {
    $schedule = discoverSchedule(ScheduledCommand::class);

    expect($schedule->events())->toHaveCount(1);

    $event = $schedule->events()[0];

    expect($event->expression)->toBe('0 0 * * *');
    expect($event->command)->toContain('fixture:scheduled');
});

it('schedules a queued job via a class-level Scheduled attribute', function () {
    $schedule = discoverSchedule(ProcessOrdersJob::class);

    expect($schedule->events())->toHaveCount(1);

    $event = $schedule->events()[0];

    expect($event->expression)->toBe('0 * * * *');
    expect($event->description)->toBe(ProcessOrdersJob::class . '#0');
    expect($event->command)->toBeNull();
});

it('dispatches the scheduled job when its event runs', function () {
    Bus::fake();

    $schedule = discoverSchedule(ProcessOrdersJob::class);

    $schedule->events()[0]->run(app());

    Bus::assertDispatched(ProcessOrdersJob::class);
});

it('configures a class-level scheduled job through a closure', function () {
    $schedule = discoverSchedule(ClosureScheduledJob::class);

    expect($schedule->events())->toHaveCount(1);

    $event = $schedule->events()[0];

    expect($event->expression)->toBe('* * * * *');
    expect($event->onOneServer)->toBeTrue();
});

it('throws when a class-level Scheduled attribute is used on a class that is neither a command nor a job', function () {
    discoverSchedule(ClassScheduledNonCommand::class);
})->throws(
    \LogicException::class,
    'Scheduled class ' . ClassScheduledNonCommand::class . ' must either be a command extending ' . Command::class . ' or a job implementing ' . ShouldQueue::class,
);

it('passes parameters to a scheduled callback', function () {
    ParameterizedCallbackTask::$received = [];

    $schedule = discoverSchedule(ParameterizedCallbackTask::class);

    $schedule->events()[0]->run(app());

    expect(ParameterizedCallbackTask::$received)->toBe([
        'city'  => 'Amsterdam',
        'limit' => 5,
    ]);
});

it('passes parameters to a scheduled command', function () {
    $schedule = discoverSchedule(ParameterizedScheduledCommand::class);

    expect($schedule->events())->toHaveCount(1);

    $event = $schedule->events()[0];

    expect($event->command)->toContain('inventory:rebuild');
    expect($event->command)->toContain('--queue=');
    expect($event->command)->toContain('orders');
});

it('registers one event per Scheduled attribute on a command class', function () {
    $schedule = discoverSchedule(MultipleScheduledCommand::class);

    expect($schedule->events())->toHaveCount(2);

    $expressions = array_column($schedule->events(), 'expression');
    expect($expressions)->toContain('0 * * * *');   // Every::Hour
    expect($expressions)->toContain('0 9 * * 1');   // Cron: Mondays at 09:00

    foreach ($schedule->events() as $event) {
        expect($event->command)->toContain('reports:dispatch');
    }
});

it('registers one event per Scheduled attribute on a job class, resolving each closure by index', function () {
    $schedule = discoverSchedule(MultipleScheduledJob::class);

    expect($schedule->events())->toHaveCount(2);

    $hourly = collect($schedule->events())->firstWhere('expression', '0 * * * *');
    $daily  = collect($schedule->events())->firstWhere('expression', '0 0 * * *');

    expect($hourly)->not->toBeNull();
    expect($daily)->not->toBeNull();

    // Each DiscoveredSchedule re-reads its own attribute via attributeIndex.
    expect($hourly->onOneServer)->toBeTrue();
    expect($daily->withoutOverlapping)->toBeTrue();

    // CallbackEvents (jobs), not command events.
    expect($hourly->command)->toBeNull();
    expect($daily->command)->toBeNull();
});
