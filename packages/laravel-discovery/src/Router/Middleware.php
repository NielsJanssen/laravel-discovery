<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Middleware implements RouteDecorator
{
    /**
     * @param list<class-string|string> $middleware Middleware to add to this route.
     * @param list<class-string|string> $without Middleware to remove from this route.
     */
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
