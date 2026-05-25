<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Middleware implements RouteDecorator
{
    public function __construct(
        public array $middleware,
        public array $without = [],
    ) {}

    public function decorate(Routable $route): Routable
    {
        $route->middleware        = array_merge($this->middleware, $route->middleware);
        $route->withoutMiddleware = array_merge($route->withoutMiddleware, $this->without);

        return $route;
    }
}
