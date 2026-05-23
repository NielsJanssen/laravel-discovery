<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Get;

class NamedRouteController
{
    #[Get('/named', name: 'named.route')]
    public function index(): void {}
}
