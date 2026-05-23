<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Domain implements RouteDecorator
{
    public function __construct(
        public string|\BackedEnum $name,
    ) {}

    public function decorate(Route $route): Route
    {
        $route->domain = $this->name;

        return $route;
    }
}
