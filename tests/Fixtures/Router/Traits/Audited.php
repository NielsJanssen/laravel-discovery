<?php

declare(strict_types=1);

namespace Tests\Fixtures\Router\Traits;

use NielsJanssen\Laravel\Discovery\Router\Domain;
use NielsJanssen\Laravel\Discovery\Router\Middleware;
use NielsJanssen\Laravel\Discovery\Router\Prefix;

#[Prefix('audit')]
#[Domain('audit.acme.test')]
#[Middleware(['audit'])]
trait Audited {}
