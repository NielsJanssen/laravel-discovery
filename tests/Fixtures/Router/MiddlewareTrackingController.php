<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Feature\Router\Get;

class MiddlewareTrackingController
{
    #[Get('/tracked', middleware: [TrackingMiddleware::class])]
    public function index(): string
    {
        return 'ok';
    }
}
