<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;

#[Scoped]
class RouteDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private readonly Application $app,
        private readonly Router $route,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        $classDecorators = $class->getAttributes(RouteDecorator::class);

        foreach ($class->getPublicMethods() as $method) {
            foreach ($method->getAttributes(Routable::class) as $route) {
                $decorators = [
                    ...$classDecorators,
                    ...$method->getAttributes(RouteDecorator::class),
                ];

                $this->discoveryItems->add($location, DiscoveredRoute::from($route, $decorators, $method));
            }
        }
    }

    public function apply(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $named = false;

        foreach ($this->discoveryItems as $discoveredRoute) {
            $route = $this->route->addRoute(
                methods: array_map(
                    static fn(Method $method) => $method->value,
                    $discoveredRoute->methods,
                ),
                uri: $discoveredRoute->uri,
                action: $discoveredRoute->action,
            )
                ->middleware($discoveredRoute->middleware)
                ->withoutMiddleware($discoveredRoute->withoutMiddleware);

            if ($discoveredRoute->domain !== null) {
                $route->domain($discoveredRoute->domain);
            }

            if ($discoveredRoute->name !== null) {
                $route->name($discoveredRoute->name);
                $named = true;
            }
        }

        // Laravel populates the name lookup table only when a route is added
        // to the collection; names set afterwards via the fluent API need a
        // manual refresh, otherwise route() and getByName() can't find them.
        if ($named) {
            $this->route->getRoutes()->refreshNameLookups();
        }
    }
}
