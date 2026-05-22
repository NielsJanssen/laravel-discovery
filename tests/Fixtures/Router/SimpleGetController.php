<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Get;

class SimpleGetController
{
    #[Get('/simple')]
    public function index(): void {}
}
