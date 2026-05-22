<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery;

use Illuminate\Support\ServiceProvider;
use NielsJanssen\Laravel\Discovery\Commands\MakeDiscoveryCommand;
use NielsJanssen\Laravel\Discovery\Feature\Command\CommandDiscovery;
use NielsJanssen\Laravel\Discovery\Feature\Event\EventDiscovery;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\DiscoveryConfig;

class DiscoveryServiceProvider extends ServiceProvider
{
    private const array FEATURES = [
        CommandDiscovery::class,
        EventDiscovery::class,
    ];

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

        foreach (self::FEATURES as $feature) {
            $feature::register($this->app, $discoveryConfig);
        }

        new BootDiscovery(
            container: $this->app,
            config: $discoveryConfig,
            // cache: $cache, // TODO: add caching
        )(static::FEATURES);
    }
}
