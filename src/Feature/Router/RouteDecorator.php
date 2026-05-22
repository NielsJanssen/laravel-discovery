<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Router;

interface RouteDecorator
{
    public function decorate(Route $route): Route;
}
