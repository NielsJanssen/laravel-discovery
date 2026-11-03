<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router\Traits;

use NielsJanssen\Laravel\Discovery\Router\Domain;
use NielsJanssen\Laravel\Discovery\Router\Get;
use NielsJanssen\Laravel\Discovery\Router\Middleware;
use NielsJanssen\Laravel\Discovery\Router\Prefix;

#[Prefix('admin')]
#[Domain('admin.acme.test')]
#[Middleware(['auth'])]
class TraitOverriddenController
{
    use ServesApi;

    #[Get('/users')]
    public function index(): void {}

    #[Get('/status')]
    #[Domain('status.acme.test')]
    public function status(): void {}
}
