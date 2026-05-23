<?php

declare(strict_types=1);

namespace Tests;

use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DiscoveryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('discovery.autoload', dirname(__DIR__));

        $app[DiscoveryConfig::class]->locations[] = new DiscoveryLocation(
            namespace: 'Workbench\\App',
            path: dirname(__DIR__) . '/workbench/app',
        );
    }
}
