<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Middleware;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Schema;
use Tests\Fixtures\RebingGraphQL\AlwaysAllowGate;
use Workbench\App\GraphQL\Middleware\UppercaseMiddleware;

#[Schema('reports')]
#[Middleware(UppercaseMiddleware::class)]
#[Authorize(gate: AlwaysAllowGate::class)]
trait ForReports {}
