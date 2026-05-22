<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Router\Get;
use NielsJanssen\Laravel\Discovery\Router\Prefix;

#[Prefix('api')]
class PrefixedController
{
    #[Get('/users')]
    public function index(): void {}
}
