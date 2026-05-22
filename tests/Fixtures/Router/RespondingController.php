<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Get;

class RespondingController
{
    #[Get('/respond')]
    public function index(): string
    {
        return 'ok';
    }
}
