<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Feature\Router\Get;
use NielsJanssen\Laravel\Discovery\Feature\Router\Middleware;

#[Middleware(['auth'])]
class MiddlewareController
{
    #[Get('/protected')]
    public function index(): void {}

    #[Get('/partial', withoutMiddleware: ['auth'])]
    public function partial(): void {}
}
