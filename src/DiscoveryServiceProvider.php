<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery;

use Illuminate\Support\ServiceProvider;
use NielsJanssen\Laravel\Discovery\Command\CommandDiscovery;
use NielsJanssen\Laravel\Discovery\Event\EventDiscovery;
use NielsJanssen\Laravel\Discovery\Laravel\MakeDiscoveryCommand;
use NielsJanssen\Laravel\Discovery\Router\RouteDiscovery;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\DiscoveryConfig;

class DiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            MakeDiscoveryCommand::class,
        ]);

        $this->mergeConfigFrom(
            __DIR__ . '/../config/discovery.php',
            'discovery',
        );
    }

    public function boot(): void
    {
        $config = $this->app->make('config');

        $discoveryConfig = DiscoveryConfig::autoload($config->get('discovery.autoload'))
            ->skipClasses(...$config->get('discovery.skip_classes') ?? [])
            ->skipPaths(...$config->get('discovery.skip_paths') ?? []);

        new BootDiscovery(
            container: $this->app,
            config: $discoveryConfig,
            // cache: $cache, // TODO: add caching
        )([
            CommandDiscovery::class,
            EventDiscovery::class,
            RouteDiscovery::class,
        ]);
    }
}
