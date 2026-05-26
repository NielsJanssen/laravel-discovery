<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Get;

#[Get('/invokable')]
class InvokableRouteController
{
    public function __invoke(): string
    {
        return 'ok';
    }
}
