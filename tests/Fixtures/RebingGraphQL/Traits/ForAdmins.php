<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Middleware;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;
use Tests\Fixtures\RebingGraphQL\AlwaysDenyGate;
use Workbench\App\GraphQL\Middleware\ExclamationMiddleware;

#[Schema('admin')]
#[Middleware(ExclamationMiddleware::class)]
#[Authorize(gate: AlwaysDenyGate::class, message: 'Admins only')]
trait ForAdmins {}
