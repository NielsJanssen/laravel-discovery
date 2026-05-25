<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Router;

interface RouteDecorator
{
    public function decorate(Routable $route): Routable;
}
