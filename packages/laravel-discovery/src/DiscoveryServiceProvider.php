<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use NielsJanssen\Laravel\Discovery\Cache\MemoryAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\CouldNotStoreDiscoveryCache;
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

            /** @var list<string> $skipClasses */
            $skipClasses = $config->collection('discovery.skip_classes', [])
                ->values()
                ->ensure('string') // @phpstan-ignore argument.type (PhpStan does not understand that 'string' is a valid argument)
                ->all();

            /** @var list<string> $skipPaths */
            $skipPaths = $config->collection('discovery.skip_paths', [])
                ->values()
                ->ensure('string') // @phpstan-ignore argument.type (PhpStan does not understand that 'string' is a valid argument)
                ->all();

            return DiscoveryConfig::autoload($config->string('discovery.autoload'))
                ->skipClasses(...$skipClasses)
                ->skipPaths(...$skipPaths);
        });

        $this->app->singleton(DiscoveryCache::class, function () {
            $config = $this->app->make('config');

            return new DiscoveryCache(
                strategy: $this->app->environment($config->array('discovery.cache_environments', ['production']))
                    ? DiscoveryCacheStrategy::FULL
                    : DiscoveryCacheStrategy::NONE,
                pool: match ($config->string('discovery.cache_store', 'files')) {
                    'memory' => MemoryAdapter::forProcess(),
                    default => new PhpFilesAdapter(
                        directory: storage_path($this->app->make('config')->string('discovery.cache_path', 'framework/cache/discovery')),
                    ),
                },
            );
        });

        $this->optimizes('discovery:cache', 'discovery:clear');

        $this->publishes([
            __DIR__ . '/../config/discovery.php' => config_path('discovery.php'),
        ], 'discovery-config');
    }

    public function boot(): void
    {
        /** @var Discovery[] $discoveries */
        $discoveries = $this->app->call(BootDiscovery::class);

        $this->warmMemoryCache($discoveries);

        $this->app->make('config')->set(
            'discovery.discovery_classes',
            array_map(
                static fn(Discovery $discovery) => $discovery::class,
                $discoveries,
            ),
        );
    }

    /**
     * Warmup the in-process memory cache if it is enabled.
     *
     * @param Discovery[] $discoveries
     *
     * @throws BindingResolutionException
     * @throws CouldNotStoreDiscoveryCache
     */
    private function warmMemoryCache(array $discoveries): void
    {
        if ($this->app->make('config')->string('discovery.cache_store') !== 'memory') {
            return;
        }

        $pool = MemoryAdapter::forProcess();
        $cache = $this->app->make(DiscoveryCache::class);

        if (! $cache->enabled || $pool->isWarm()) {
            return;
        }

        $pool->markWarm();

        foreach ($this->app->make(DiscoveryConfig::class)->locations as $location) {
            $cache->store($location, $discoveries);
        }
    }
}
