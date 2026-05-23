<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Get;

class EnumNamedRouteController
{
    #[Get('/enum-named', name: RouteName::EnumNamed)]
    public function index(): void {}
}
