<?php

declare(strict_types=1);

namespace Tests;

use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Rebing\GraphQL\GraphQLServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DiscoveryServiceProvider::class,
            GraphQLServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void {}
}
