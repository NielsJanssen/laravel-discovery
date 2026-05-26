<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Router;
use NielsJanssen\Laravel\Discovery\Router\Method;
use NielsJanssen\Laravel\Discovery\Router\RouteDiscovery;
use Tempest\Discovery\DiscoveryItems;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Reflection\ClassReflector;
use Tests\Fixtures\Router\AllMethodsController;
use Tests\Fixtures\Router\DomainController;
use Tests\Fixtures\Router\EnumDomainController;
use Tests\Fixtures\Router\EnumNamedRouteController;
use Tests\Fixtures\Router\MethodPrefixController;
use Tests\Fixtures\Router\MiddlewareController;
use Tests\Fixtures\Router\MiddlewareTrackingController;
use Tests\Fixtures\Router\NamedRouteController;
use Tests\Fixtures\Router\NoRouteController;
use Tests\Fixtures\Router\PrefixedController;
use Tests\Fixtures\Router\RepeatableRouteController;
use Tests\Fixtures\Router\RespondingController;
use Tests\Fixtures\Router\RouteDomain;
use Tests\Fixtures\Router\RouteLog;
use Tests\Fixtures\Router\RouteName;
use Tests\Fixtures\Router\MultiMethodController;
use Tests\Fixtures\Router\SimpleGetController;
use Tests\Fixtures\Router\InvokableRouteController;
use Tests\Fixtures\Router\TrackingMiddleware;

function discoverRoutes(string ...$classes): RouteDiscovery
{
    $discovery = app(RouteDiscovery::class);
    $discovery->setItems(new DiscoveryItems());

    $location = new DiscoveryLocation(
        namespace: 'Tests\\Fixtures\\Router',
        path: dirname(__DIR__, 2) . '/Fixtures/Router',
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
            methods: array_map(
                static fn(Method $method) => $method->value,
                $discoveredRoute->methods,
            ),
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
        expect($routes[0]->methods)->toBe([Method::Get]);
        expect($routes[0]->uri)->toBe('/simple');
        expect($routes[0]->action)->toBe(SimpleGetController::class . '@index');
    });

    it('discovers one route per HTTP verb', function () {
        $routes = [...discoverRoutes(AllMethodsController::class)->getItems()];

        $methods = array_map(fn($r) => $r->methods, $routes);

        expect($routes)->toHaveCount(7);
        expect($methods)->toContain([Method::Get]);
        expect($methods)->toContain([Method::Post]);
        expect($methods)->toContain([Method::Put]);
        expect($methods)->toContain([Method::Patch]);
        expect($methods)->toContain([Method::Delete]);
        expect($methods)->toContain([Method::Head]);
        expect($methods)->toContain([Method::Options]);
    });

    it('discovers multiple routes from a repeatable attribute on one method', function () {
        $routes = [...discoverRoutes(RepeatableRouteController::class)->getItems()];

        $uris = array_map(fn($r) => $r->uri, $routes);

        expect($routes)->toHaveCount(2);
        expect($uris)->toContain('/v1/items');
        expect($uris)->toContain('/v2/items');
    });

    it('discovers a Route attribute with multiple methods as one route', function () {
        $routes = [...discoverRoutes(MultiMethodController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->methods)->toBe([Method::Get, Method::Post]);
        expect($routes[0]->uri)->toBe('/multi');
        expect($routes[0]->action)->toBe(MultiMethodController::class . '@index');
    });

    it('skips classes with no route attributes', function () {
        $routes = [...discoverRoutes(NoRouteController::class)->getItems()];

        expect($routes)->toHaveCount(0);
    });
});

describe('class routes', function () {
    it('discovers a route attribute placed on the class itself', function () {
        $routes = [...discoverRoutes(InvokableRouteController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->methods)->toBe([Method::Get]);
        expect($routes[0]->uri)->toBe('/invokable');
    });

    it('sets the action to the class name without a method suffix', function () {
        $routes = [...discoverRoutes(InvokableRouteController::class)->getItems()];

        expect($routes[0]->action)->toBe(InvokableRouteController::class);
    });

    it('handles an HTTP request to a class-level discovered route', function () {
        discoverRoutes(InvokableRouteController::class);

        $this->get('/invokable')->assertOk();
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

    it('leaves the name null when not specified', function () {
        $routes = [...discoverRoutes(SimpleGetController::class)->getItems()];

        expect($routes[0]->name)->toBeNull();
    });

    it('sets the name from the route attribute', function () {
        $routes = [...discoverRoutes(NamedRouteController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->name)->toBe('named.route');
    });

    it('accepts a BackedEnum as the name on the route attribute', function () {
        $routes = [...discoverRoutes(EnumNamedRouteController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->name)->toBe(RouteName::EnumNamed);
    });

    it('accepts a BackedEnum as the domain on the Domain decorator', function () {
        $routes = [...discoverRoutes(EnumDomainController::class)->getItems()];

        expect($routes)->toHaveCount(1);
        expect($routes[0]->domain)->toBe(RouteDomain::Api);
    });
});

describe('route name registration', function () {
    it('registers a named route on the Laravel router', function () {
        discoverRoutes(NamedRouteController::class);

        $route = app(Router::class)->getRoutes()->getByName('named.route');

        expect($route)->not->toBeNull();
        expect($route->uri())->toBe('named');
    });

    it('resolves a BackedEnum name to its string value when registering', function () {
        discoverRoutes(EnumNamedRouteController::class);

        $route = app(Router::class)->getRoutes()->getByName(RouteName::EnumNamed->value);

        expect($route)->not->toBeNull();
        expect($route->uri())->toBe('enum-named');
    });

    it('resolves a BackedEnum domain to its string value when registering', function () {
        discoverRoutes(EnumDomainController::class);

        $route = app(Router::class)
            ->getRoutes()
            ->getByAction(EnumDomainController::class . '@index');

        expect($route)->not->toBeNull();
        expect($route->getDomain())->toBe(RouteDomain::Api->value);
    });

    it('does not register a name on routes that omit it', function () {
        discoverRoutes(SimpleGetController::class);

        $route = app(Router::class)
            ->getRoutes()
            ->getByAction(SimpleGetController::class . '@index');

        expect($route)->not->toBeNull();
        expect($route->getName())->toBeNull();
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

    it('registers a Route attribute with multiple methods on all specified verbs', function () {
        $router = registerRoutes(MultiMethodController::class);

        $route = $router->getRoutes()->getByAction(MultiMethodController::class . '@index');

        expect($route)->not->toBeNull();
        expect($route->methods())->toContain('GET');
        expect($route->methods())->toContain('POST');

        $this->get('/multi')->assertOk();
        $this->post('/multi')->assertOk();
    });

    it('registers withoutMiddleware on the route', function () {
        $router = registerRoutes(MiddlewareController::class);

        $route = $router->getRoutes()->getByAction(MiddlewareController::class . '@partial');

        expect($route->excludedMiddleware())->toContain('auth');
    });
});
