<?php

declare(strict_types=1);
use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use Rebing\GraphQL\GraphQLServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

return [
    DiscoveryServiceProvider::class,
    GraphQLServiceProvider::class,
    WorkbenchServiceProvider::class,
];
