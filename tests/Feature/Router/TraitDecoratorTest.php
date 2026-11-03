<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\Router\DiscoveredRoute;
use NielsJanssen\Laravel\Discovery\Router\RouteDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Router\Traits\ChildOfTraitDecoratedController;
use Tests\Fixtures\Router\Traits\NestedTraitController;
use Tests\Fixtures\Router\Traits\TraitDecoratedController;
use Tests\Fixtures\Router\Traits\TraitOverriddenController;

/**
 * @param  class-string  $class
 * @return array<string, DiscoveredRoute> keyed by action
 */
function traitDecoratedRoutes(string $class): array
{
    $discovery = app(RouteDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $discovery->discover(new DiscoveryLocation('Tests\\Fixtures\\Router\\Traits', dirname(__DIR__, 2) . '/Fixtures/Router/Traits'), new ClassReflector($class));

    $routes = [];

    foreach ($discovery->getItems() as $route) {
        $routes[$route->action] = $route;
    }

    return $routes;
}

it('applies the decorators of the traits a class uses, the first trait winning', function () {
    $route = traitDecoratedRoutes(TraitDecoratedController::class)[TraitDecoratedController::class . '@index'];

    expect($route->uri)->toBe('v1/audit/users')
        ->and($route->domain)->toBe('api.acme.test')
        ->and($route->middleware)->toBe(['api', 'audit'])
        ->and($route->withoutMiddleware)->toBe(['web']);
});

it('lets the class own decorators win over its traits, and the method over both', function () {
    $routes = traitDecoratedRoutes(TraitOverriddenController::class);
    $index = $routes[TraitOverriddenController::class . '@index'];
    $status = $routes[TraitOverriddenController::class . '@status'];

    expect($index->uri)->toBe('admin/v1/users')
        ->and($index->domain)->toBe('admin.acme.test')
        ->and($index->middleware)->toBe(['auth', 'api'])
        ->and($status->uri)->toBe('admin/v1/status')
        ->and($status->domain)->toBe('status.acme.test');
});

it('does not read a route attribute from a trait', function () {
    expect(array_keys(traitDecoratedRoutes(TraitDecoratedController::class)))
        ->toBe([TraitDecoratedController::class . '@index']);
});

it('reads only the traits a class uses directly', function (string $class, string $action, string $uri) {
    $route = traitDecoratedRoutes($class)[$action];

    expect($route->uri)->toBe($uri)
        ->and($route->domain)->toBeNull()
        ->and($route->middleware)->toBe([]);
})->with([
    'a trait used by a trait' => [NestedTraitController::class, NestedTraitController::class . '@index', '/nested'],
    'a trait used by the parent class' => [ChildOfTraitDecoratedController::class, TraitDecoratedController::class . '@index', '/users'],
]);

it('keeps trait decorators through a discovery cache round trip', function () {
    $routes = unserialize(serialize(traitDecoratedRoutes(TraitDecoratedController::class)));
    $route = $routes[TraitDecoratedController::class . '@index'];

    expect($route)->toBeInstanceOf(DiscoveredRoute::class)
        ->and($route->uri)->toBe('v1/audit/users')
        ->and($route->middleware)->toBe(['api', 'audit']);
});
