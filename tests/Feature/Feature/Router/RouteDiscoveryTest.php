<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Router;
use NielsJanssen\Laravel\Discovery\Feature\Router\Method;
use NielsJanssen\Laravel\Discovery\Feature\Router\RouteDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Router\AllMethodsController;
use Tests\Fixtures\Router\DomainController;
use Tests\Fixtures\Router\MethodPrefixController;
use Tests\Fixtures\Router\MiddlewareController;
use Tests\Fixtures\Router\MiddlewareTrackingController;
use Tests\Fixtures\Router\NoRouteController;
use Tests\Fixtures\Router\PrefixedController;
use Tests\Fixtures\Router\RepeatableRouteController;
use Tests\Fixtures\Router\RespondingController;
use Tests\Fixtures\Router\RouteLog;
use Tests\Fixtures\Router\SimpleGetController;
use Tests\Fixtures\Router\TrackingMiddleware;

function discoverRoutes(string ...$classes): RouteDiscovery
{
    $discovery = app(RouteDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Router',
        path: dirname(__DIR__, 3) . '/Fixtures/Router',
    );

    foreach ($classes as $class) {
        $discovery->discover($location, new ClassReflector($class));
    }

    $discovery->apply();

    return $discovery;
}

function registerRoutes(string ...$classes): Router
{
    $discovery = discoverRoutes(...$classes);
    $router = app(Router::class);

    foreach ($discovery->getItems() as $discoveredRoute) {
        $router->addRoute(
            methods: [$discoveredRoute->method->value],
            uri: $discoveredRoute->uri,
            action: $discoveredRoute->action,
        )
            ->middleware($discoveredRoute->middleware)
            ->withoutMiddleware($discoveredRoute->withoutMiddleware)
            ->domain($discoveredRoute->domain);
    }

    return $router;
}

describe('discovery', function () {
    it('discovers a GET route with correct method, URI, and action', function () {
        $routes = [...discoverRoutes(SimpleGetController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->method)->toBe(Method::Get);
        expect($routes[0]->uri)->toBe('/simple');
        expect($routes[0]->action)->toBe(SimpleGetController::class . '@index');
    });

    it('discovers one route per HTTP verb', function () {
        $routes = [...discoverRoutes(AllMethodsController::class)->getItems()];

        $methods = array_map(fn($r) => $r->method, $routes);

        expect($routes)->toHaveCount(7);
        expect($methods)->toContain(Method::Get);
        expect($methods)->toContain(Method::Post);
        expect($methods)->toContain(Method::Put);
        expect($methods)->toContain(Method::Patch);
        expect($methods)->toContain(Method::Delete);
        expect($methods)->toContain(Method::Head);
        expect($methods)->toContain(Method::Options);
    });

    it('discovers multiple routes from a repeatable attribute on one method', function () {
        $routes = [...discoverRoutes(RepeatableRouteController::class)->getItems()];

        $uris = array_map(fn($r) => $r->uri, $routes);

        expect($routes)->toHaveCount(2);
        expect($uris)->toContain('/v1/items');
        expect($uris)->toContain('/v2/items');
    });

    it('skips classes with no route attributes', function () {
        $routes = [...discoverRoutes(NoRouteController::class)->getItems()];

        expect($routes)->toHaveCount(0);
    });
});

describe('decorators', function () {
    it('applies a class-level prefix to the URI', function () {
        $routes = [...discoverRoutes(PrefixedController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->uri)->toBe('api/users');
    });

    it('applies a method-level prefix to the URI', function () {
        $routes = [...discoverRoutes(MethodPrefixController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->uri)->toBe('v1/items');
    });

    it('applies class-level middleware to the discovered route', function () {
        $routes = [...discoverRoutes(MiddlewareController::class)->getItems()];

        $protected = collect($routes)->firstWhere('uri', '/protected');

        expect($protected->middleware)->toContain('auth');
    });

    it('applies withoutMiddleware from the route attribute', function () {
        $routes = [...discoverRoutes(MiddlewareController::class)->getItems()];

        $partial = collect($routes)->firstWhere('uri', '/partial');

        expect($partial->withoutMiddleware)->toContain('auth');
    });

    it('sets the domain from the route attribute', function () {
        $routes = [...discoverRoutes(DomainController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->domain)->toBe('api.example.com');
    });
});

describe('registration', function () {
    it('registers the route in the Laravel router route collection', function () {
        $router = registerRoutes(RespondingController::class);

        $route = $router->getRoutes()->getByAction(RespondingController::class . '@index');

        expect($route)->not->toBeNull();
        expect($route->methods())->toContain('GET');
        expect($route->uri())->toBe('respond');
    });

    it('handles an HTTP request to a discovered route', function () {
        registerRoutes(RespondingController::class);

        $this->get('/respond')->assertOk();
    });

    it('registers the correct middleware on the route', function () {
        $router = registerRoutes(MiddlewareTrackingController::class);

        $route = $router->getRoutes()->getByAction(MiddlewareTrackingController::class . '@index');

        expect($route->gatherMiddleware())->toContain(TrackingMiddleware::class);
    });

    it('executes route middleware when the route is called', function () {
        RouteLog::$calls = [];

        registerRoutes(MiddlewareTrackingController::class);

        $this->get('/tracked');

        expect(RouteLog::$calls)->toContain(TrackingMiddleware::class);
    });

    it('registers withoutMiddleware on the route', function () {
        $router = registerRoutes(MiddlewareController::class);

        $route = $router->getRoutes()->getByAction(MiddlewareController::class . '@partial');

        expect($route->excludedMiddleware())->toContain('auth');
    });
});
