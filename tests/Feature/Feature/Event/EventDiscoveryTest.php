<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Events\Dispatcher;
use NielsJanssen\Laravel\Discovery\Event\EventDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Event\EventA;
use Tests\Fixtures\Event\EventB;
use Tests\Fixtures\Event\ExplicitEventListener;
use Tests\Fixtures\Event\MixedUnionEventListener;
use Tests\Fixtures\Event\NoParameterListener;
use Tests\Fixtures\Event\ScalarParameterListener;
use Tests\Fixtures\Event\SingleEventListener;
use Tests\Fixtures\Event\TrackingEventListener;
use Tests\Fixtures\Event\UnattributedListener;
use Tests\Fixtures\Event\UnionEventListener;

function discoverEvents(string ...$classes): Dispatcher
{
    $dispatcher = app(Dispatcher::class);
    $discovery  = new EventDiscovery($dispatcher);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Event',
        path: dirname(__DIR__, 3) . '/Fixtures/Event',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    $discovery->apply();

    return $dispatcher;
}

it('registers a listener inferred from a single class-typed parameter', function () {
    $listeners = discoverEvents(SingleEventListener::class)->getRawListeners();

    expect($listeners)
        ->toHaveKey(EventA::class);

    expect($listeners[EventA::class])
        ->toContain(SingleEventListener::class . '@handle');
});

it('registers listeners for each type in a union parameter', function () {
    $listeners = discoverEvents(UnionEventListener::class)->getRawListeners();

    expect($listeners)
        ->toHaveKey(EventA::class)
        ->toHaveKey(EventB::class);

    expect($listeners[EventA::class])
        ->toContain(UnionEventListener::class . '@handle');

    expect($listeners[EventB::class])
        ->toContain(UnionEventListener::class . '@handle');
});

it('filters out non-class types from a union parameter', function () {
    $listeners = discoverEvents(MixedUnionEventListener::class)->getRawListeners();

    expect($listeners)
        ->toHaveKey(EventA::class)
        ->not->toHaveKey('string');
});

it('uses the explicit event name on the attribute over the parameter type', function () {
    $listeners = discoverEvents(ExplicitEventListener::class)->getRawListeners();

    expect($listeners)
        ->toHaveKey(EventB::class)
        ->not->toHaveKey(EventA::class);
});

it('skips methods with no parameters', function () {
    $listeners = collect(discoverEvents(NoParameterListener::class)->getRawListeners())->flatten()->all();

    expect($listeners)
        ->not->toContain(NoParameterListener::class . '@handle');
});

it('skips methods with scalar-typed parameters', function () {
    $listeners = collect(discoverEvents(ScalarParameterListener::class)->getRawListeners())->flatten()->all();

    expect($listeners)
        ->not->toContain(ScalarParameterListener::class . '@handle');
});

it('skips methods without the EventHandler attribute', function () {
    $listeners = collect(discoverEvents(UnattributedListener::class)->getRawListeners())->flatten()->all();

    expect($listeners)
        ->not->toContain(UnattributedListener::class . '@handle');
});

it('registers the listener in ClassName@methodName format', function () {
    $listeners = discoverEvents(SingleEventListener::class)->getRawListeners();

    expect($listeners[EventA::class])
        ->toContain(SingleEventListener::class . '@handle');
});

it('fires the listener when the event is dispatched', function () {
    TrackingEventListener::$invoked = false;

    discoverEvents(TrackingEventListener::class);

    event(new EventA());

    expect(TrackingEventListener::$invoked)->toBeTrue();
});
