<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Prefix implements RouteDecorator
{
    public function __construct(
        public string $prefix,
    ) {}

    public function decorate(Routable $route): Routable
    {
        $route->uri = $this->prefix . '/' . ltrim($route->uri, '/');

        return $route;
    }
}
