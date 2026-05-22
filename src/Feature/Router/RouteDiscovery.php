<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Router;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use NielsJanssen\Laravel\Discovery\Feature\Feature;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Discovery\SkipDiscovery;
use Tempest\Reflection\ClassReflector;

#[Scoped]
#[SkipDiscovery]
class RouteDiscovery implements Discovery, Feature
{
    use IsDiscovery;

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

    public function apply(): void {}

    public static function register(Application $app, DiscoveryConfig $config): void
    {
        $app->afterResolving(Router::class, function (Router $router) use ($app) {
            if ($app->routesAreCached()) {
                return;
            }

            /** @var \NielsJanssen\Laravel\Discovery\Feature\Router\DiscoveredRoute $discoveredRoute */
            foreach ($app->make(self::class)->discoveryItems as $discoveredRoute) {
                $router->addRoute(
                    methods: [$discoveredRoute->method->value],
                    uri: $discoveredRoute->uri,
                    action: $discoveredRoute->action,
                )
                    ->middleware($discoveredRoute->middleware)
                    ->withoutMiddleware($discoveredRoute->withoutMiddleware)
                    ->domain($discoveredRoute->domain);
            }
        });
    }
}
