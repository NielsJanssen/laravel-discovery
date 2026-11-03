<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router\Traits;

use NielsJanssen\Laravel\Discovery\Router\Get;

class NestedTraitController
{
    use WrapsServesApi;

    #[Get('/nested')]
    public function index(): void {}
}
