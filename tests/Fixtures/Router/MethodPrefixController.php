<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router;

use NielsJanssen\Laravel\Discovery\Feature\Router\Get;
use NielsJanssen\Laravel\Discovery\Feature\Router\Prefix;

class MethodPrefixController
{
    #[Prefix('v1')]
    #[Get('/items')]
    public function index(): void {}
}
