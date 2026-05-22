<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Feature\Router\Get;

class RepeatableRouteController
{
    #[Get('/v1/items')]
    #[Get('/v2/items')]
    public function index(): void {}
}
