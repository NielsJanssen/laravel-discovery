<?php

declare(strict_types=1);

namespace Tests;

use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

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
        $app['config']->set('discovery.autoload', dirname(__DIR__) . '/workbench');
        $app['config']->set('discovery.skip_paths', []);
    }
}
