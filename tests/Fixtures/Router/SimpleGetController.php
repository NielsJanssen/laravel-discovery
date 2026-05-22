<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Feature\Router\Get;

class SimpleGetController
{
    #[Get('/simple')]
    public function index(): void {}
}
