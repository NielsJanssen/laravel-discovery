<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Router;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Reflection\ClassReflector;

#[Scoped]
#[SkipDiscovery]
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
            foreach ($method->getAttributes(Route::class) as $route) {
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

        foreach ($this->discoveryItems as $discoveredRoute) {
            $this->route->addRoute(
                methods: [$discoveredRoute->method->value],
                uri: $discoveredRoute->uri,
                action: $discoveredRoute->action,
            )
                ->middleware($discoveredRoute->middleware)
                ->withoutMiddleware($discoveredRoute->withoutMiddleware)
                ->domain($discoveredRoute->domain);
        }
    }
}
