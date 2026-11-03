<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router\Traits;

use NielsJanssen\Laravel\Discovery\Router\Domain;
use NielsJanssen\Laravel\Discovery\Router\Get;
use NielsJanssen\Laravel\Discovery\Router\Middleware;
use NielsJanssen\Laravel\Discovery\Router\Prefix;

#[Prefix('v1')]
#[Domain('api.acme.test')]
#[Middleware(['api'], without: ['web'])]
#[Get('/from-trait')]
trait ServesApi {}
