<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app->make('config');

        $config->set('cache.default', 'file');
        $config->set('discovery.autoload', dirname(__DIR__, 3));

        $this->app->afterResolving(DiscoveryConfig::class, function (DiscoveryConfig $config) {
            $config->locations[] = new DiscoveryLocation(
                namespace: 'Workbench\\App',
                path: dirname(__DIR__),
            );
        });
    }
}
