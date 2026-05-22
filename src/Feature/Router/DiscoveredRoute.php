<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Router;

use Tempest\Reflection\MethodReflector;

class DiscoveredRoute
{
    public function __construct(
        public Method $method,
        public string $uri,
        public string $action,
        public ?string $domain = null,
        public array $middleware = [],
        public array $withoutMiddleware = [],
    ) {}

    /**
     * @param list<\NielsJanssen\Laravel\Discovery\Feature\Router\RouteDecorator> $decorators
     */
    public static function from(Route $route, array $decorators, MethodReflector $method): self
    {
        foreach ($decorators as $decorator) {
            $route = $decorator->decorate($route);
        }

        return new self(
            $route->method,
            $route->uri,
            $method->getDeclaringClass()->getName() . '@' . $method->getName(),
            $route->domain,
            $route->middleware,
            $route->withoutMiddleware,
        );
    }
}
