<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery;

use Illuminate\Support\ServiceProvider;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;

class DiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/discovery.php',
            'discovery',
        );

        $this->app->singleton(DiscoveryConfig::class, function () {
            $config = $this->app->make('config');

            return DiscoveryConfig::autoload($config->get('discovery.autoload'))
                ->skipClasses(...$config->get('discovery.skip_classes') ?? [])
                ->skipPaths(...$config->get('discovery.skip_paths') ?? []);
        });

        $this->app->singleton(DiscoveryCache::class, function () {
            return new DiscoveryCache(
                strategy: $this->app->environment(config('discovery.cache_environments', ['production']))
                    ? DiscoveryCacheStrategy::FULL
                    : DiscoveryCacheStrategy::NONE,
                pool: new PhpFilesAdapter(
                    directory: storage_path(config('discovery.cache_path', 'framework/cache/discovery')),
                ),
            );
        });

        $this->optimizes('discovery:cache', 'discovery:clear');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/discovery.php' => config_path('discovery.php'),
        ], 'discovery-config');

        $discoveries = $this->app->call(BootDiscovery::class);

        $this->app->make('config')->set(
            'discovery.discovery_classes',
            array_map(
                static fn(Discovery $discovery) => $discovery::class,
                $discoveries,
            ),
        );
    }
}
