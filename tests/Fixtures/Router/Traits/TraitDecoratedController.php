<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router\Traits;

use NielsJanssen\Laravel\Discovery\Router\Get;

class TraitDecoratedController
{
    use ServesApi;
    use Audited;

    #[Get('/users')]
    public function index(): void {}
}
