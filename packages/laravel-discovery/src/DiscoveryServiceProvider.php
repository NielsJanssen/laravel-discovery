<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery;

use Illuminate\Support\ServiceProvider;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Tempest\Discovery\BootDiscovery;
use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;

class DiscoveryServiceProvider extends ServiceProvider
{
    private const MEMORY_STORE = 'memory';

    /** Kept on the class so it survives the container being rebuilt between tests. */
    private static ?ArrayAdapter $memoryPool = null;

    private static bool $memoryWarmed = false;

    /**
     * Drop the in-memory cache, so a test that changes what discovery should find can start over.
     */
    public static function forgetProcessCache(): void
    {
        self::$memoryPool = null;
        self::$memoryWarmed = false;
    }

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
                pool: $this->cachePool($config->string('discovery.cache_store', 'files')),
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

        $this->warmProcessCache($discoveries);

        $this->app->make('config')->set(
            'discovery.discovery_classes',
            array_map(
                static fn(Discovery $discovery) => $discovery::class,
                $discoveries,
            ),
        );
    }

    /**
     * The in-memory pool outlives the container it was built for, so a test suite that rebuilds the
     * application between tests scans once and reuses that result for the rest of the process.
     */
    private function cachePool(string $store): CacheItemPoolInterface
    {
        if ($store === self::MEMORY_STORE) {
            return self::$memoryPool ??= new ArrayAdapter();
        }

        return new PhpFilesAdapter(
            directory: storage_path($this->app->make('config')->string('discovery.cache_path', 'framework/cache/discovery')),
        );
    }

    /**
     * Fill the in-memory pool on the boot that scanned. The file pool is left to `discovery:cache`,
     * so a deployment decides when it is written rather than whichever request arrives first.
     *
     * @param Discovery[] $discoveries
     */
    private function warmProcessCache(array $discoveries): void
    {
        if (self::$memoryWarmed || $this->app->make('config')->string('discovery.cache_store', 'files') !== self::MEMORY_STORE) {
            return;
        }

        $cache = $this->app->make(DiscoveryCache::class);

        if (! $cache->enabled) {
            return;
        }

        self::$memoryWarmed = true;

        foreach ($this->app->make(DiscoveryConfig::class)->locations as $location) {
            $cache->store($location, $discoveries);
        }
    }
}
