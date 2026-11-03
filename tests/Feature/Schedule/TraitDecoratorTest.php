<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use NielsJanssen\Laravel\Discovery\Schedule\ScheduleDiscovery;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Schedule\Traits\NestedTraitTask;
use Tests\Fixtures\Schedule\Traits\TraitDecoratedCommand;
use Tests\Fixtures\Schedule\Traits\TraitDecoratedTask;
use Tests\Fixtures\Schedule\Traits\TraitOverriddenTask;

function traitScheduleLocation(): DiscoveryLocation
{
    return new DiscoveryLocation('Tests\\Fixtures\\Schedule\\Traits', dirname(__DIR__, 2) . '/Fixtures/Schedule/Traits');
}

/**
 * @param  class-string  $class
 * @return array<string, Event> keyed by description
 */
function traitDecoratedEvents(string $class, bool $cached = false): array
{
    $discovery = app(ScheduleDiscovery::class);
    $discovery->setItems(new DiscoveryItems());
    $discovery->discover(traitScheduleLocation(), new ClassReflector($class));

    if ($cached) {
        $cache = new DiscoveryCache(strategy: DiscoveryCacheStrategy::FULL, pool: new ArrayAdapter());
        $cache->store(traitScheduleLocation(), [$discovery]);

        app()->forgetInstance(Schedule::class);
        app()->forgetInstance(ScheduleDiscovery::class);

        $discovery = app(ScheduleDiscovery::class);
        $discovery->setItems(new DiscoveryItems()->addForLocation(traitScheduleLocation(), $cache->restore(traitScheduleLocation())[ScheduleDiscovery::class]));
    }

    $discovery->apply();

    $events = [];

    foreach (app(Schedule::class)->events() as $event) {
        $events[(string) $event->description] = $event;
    }

    return $events;
}

beforeEach(function () {
    app()->forgetInstance(Schedule::class);
    app()->forgetInstance(ScheduleDiscovery::class);
});

it('applies the decorators of the traits a class uses, the first trait winning', function (bool $cached) {
    $event = traitDecoratedEvents(TraitDecoratedTask::class, $cached)[TraitDecoratedTask::class . '@sync#0'];

    expect($event->timezone)->toBe('Europe/Amsterdam')
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->withoutOverlapping)->toBeTrue();
})->with(['fresh' => false, 'from the discovery cache' => true]);

it('lets the class own decorators win over its traits, and the method over both', function () {
    $events = traitDecoratedEvents(TraitOverriddenTask::class);

    expect($events[TraitOverriddenTask::class . '@sync#0']->timezone)->toBe('UTC')
        ->and($events[TraitOverriddenTask::class . '@sync#0']->onOneServer)->toBeTrue()
        ->and($events[TraitOverriddenTask::class . '@report#0']->timezone)->toBe('Asia/Tokyo');
});

it('does not read #[Scheduled] from a trait', function () {
    expect(array_keys(traitDecoratedEvents(TraitDecoratedTask::class)))
        ->toBe([TraitDecoratedTask::class . '@sync#0']);
});

it('applies trait decorators to a class-level schedule', function () {
    $events = array_values(traitDecoratedEvents(TraitDecoratedCommand::class));

    expect($events)->toHaveCount(1)
        ->and($events[0]->timezone)->toBe('Europe/London')
        ->and($events[0]->withoutOverlapping)->toBeTrue();
});

it('reads only the traits a class uses directly', function () {
    $event = traitDecoratedEvents(NestedTraitTask::class)[NestedTraitTask::class . '@sync#0'];

    expect($event->onOneServer)->toBeFalse()
        ->and($event->timezone)->not->toBe('Europe/Amsterdam');
});
