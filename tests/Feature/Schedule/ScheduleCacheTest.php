<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use NielsJanssen\Laravel\Discovery\Schedule\ScheduleDiscovery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Schedule\BasicScheduledTask;
use Tests\Fixtures\Schedule\ClosureConfiguredTask;
use Tests\Fixtures\Schedule\FrequencyEnumTask;
use Tests\Fixtures\Schedule\MultipleScheduledTask;
use Tests\Fixtures\Schedule\NamedTask;

beforeEach(function () {
    app()->forgetInstance(Schedule::class);
    app()->forgetInstance(ScheduleDiscovery::class);
});

it('all schedule item types survive a cache round-trip and apply correctly', function () {
    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Schedule',
        path: dirname(__DIR__, 2) . '/Fixtures/Schedule',
    );

    // Discover all fixture types into a single discovery instance
    $discovery = app(ScheduleDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    foreach ([BasicScheduledTask::class, FrequencyEnumTask::class, ClosureConfiguredTask::class, MultipleScheduledTask::class, NamedTask::class] as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    expect($discovery->getItems())->toHaveCount(6);

    // Store to an in-memory cache that serializes values (ArrayAdapter default)
    $cache = new DiscoveryCache(
        strategy: DiscoveryCacheStrategy::FULL,
        pool: new ArrayAdapter(),
    );
    $cache->store($location, [$discovery]);

    // Restore into a fresh discovery and apply
    $restored = $cache->restore($location);

    app()->forgetInstance(Schedule::class);
    app()->forgetInstance(ScheduleDiscovery::class);

    $fresh = app(ScheduleDiscovery::class);
    $fresh->setItems(
        (new DiscoveryItems())->addForLocation($location, $restored[ScheduleDiscovery::class]),
    );
    $fresh->apply();

    $events = app(Schedule::class)->events();
    $crons  = array_column($events, 'expression');
    $names  = array_column($events, 'description');

    // BasicScheduledTask (1) + FrequencyEnumTask (1) + ClosureConfiguredTask (1) + MultipleScheduledTask (2) + NamedTask (1)
    expect($events)->toHaveCount(6);

    expect($crons)->toContain('*/15 * * * *');  // BasicScheduledTask: '15 minutes'
    expect($crons)->toContain('0 0 * * *');      // FrequencyEnumTask: Frequency::Daily

    expect($names)->toContain('my-named-task');  // NamedTask explicit name
    expect($names)->toContain(MultipleScheduledTask::class . '@run#0');
    expect($names)->toContain(MultipleScheduledTask::class . '@run#1');

    // Closure-configured task: closure re-read from reflection after round-trip
    $closureEvent = collect($events)->firstWhere('description', ClosureConfiguredTask::class . '@run');
    expect($closureEvent->withoutOverlapping)->toBeTrue();
});
