<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Router;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Domain implements RouteDecorator
{
    public function __construct(
        public string $name,
    ) {}

    public function decorate(Route $route): Route
    {
        $route->domain = $this->name;

        return $route;
    }
}
