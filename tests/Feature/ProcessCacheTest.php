<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;

function discoveryPool(DiscoveryCache $cache): object
{
    return new ReflectionProperty(DiscoveryCache::class, 'pool')->getValue($cache);
}

/** Resolve the cache after changing config, since the container already holds the one from boot. */
function freshCache(): DiscoveryCache
{
    app()->forgetInstance(DiscoveryCache::class);

    return app(DiscoveryCache::class);
}

function useMemoryStore(): void
{
    config()->set('discovery.cache_store', 'memory');
    config()->set('discovery.cache_environments', [app()->environment()]);
    app()->forgetInstance(DiscoveryCache::class);
}

/** @return list<string> the discovery classes this boot ended up with */
function bootProvider(): array
{
    new DiscoveryServiceProvider(app())->boot();

    return config('discovery.discovery_classes');
}

/**
 * Skipping a class only takes effect while scanning, so a boot that still reports it restored the
 * cache instead of walking the filesystem.
 */
function skipOnNextScan(string $class): void
{
    config()->set('discovery.skip_classes', [$class]);
    app()->forgetInstance(DiscoveryConfig::class);
}

beforeEach(fn() => DiscoveryServiceProvider::forgetProcessCache());
afterEach(fn() => DiscoveryServiceProvider::forgetProcessCache());

describe('cache store selection', function () {
    it('uses the file pool by default', function () {
        config()->set('discovery.cache_store', 'files');

        expect(discoveryPool(freshCache()))->toBeInstanceOf(PhpFilesAdapter::class);
    });

    it('uses an in-memory pool when the store is memory', function () {
        config()->set('discovery.cache_store', 'memory');

        expect(discoveryPool(freshCache()))->toBeInstanceOf(ArrayAdapter::class);
    });

    it('hands the same in-memory pool to a rebuilt container', function () {
        config()->set('discovery.cache_store', 'memory');
        $first = discoveryPool(freshCache());

        $this->refreshApplication();
        config()->set('discovery.cache_store', 'memory');

        expect(discoveryPool(freshCache()))->toBe($first);
    });

    it('forgets the pool on request, so a run can start over', function () {
        config()->set('discovery.cache_store', 'memory');
        $first = discoveryPool(freshCache());

        DiscoveryServiceProvider::forgetProcessCache();

        expect(discoveryPool(freshCache()))->not->toBe($first);
    });

    it('leaves the strategy to cache_environments', function () {
        config()->set('discovery.cache_store', 'memory');
        config()->set('discovery.cache_environments', ['production']);

        expect(freshCache()->strategy)->toBe(DiscoveryCacheStrategy::NONE)
            ->and(freshCache()->enabled)->toBeFalse();
    });
});

describe('the in-memory cache across boots', function () {
    beforeEach(fn() => useMemoryStore());

    it('stores every location on the boot that scanned', function () {
        $cache = app(DiscoveryCache::class);

        bootProvider();

        foreach (app(DiscoveryConfig::class)->locations as $location) {
            expect($cache->restore($location))->not->toBeNull();
        }
    });

    it('restores instead of scanning on every later boot', function () {
        expect(bootProvider())->toContain(GraphQLDiscovery::class);

        skipOnNextScan(GraphQLDiscovery::class);

        expect(bootProvider())->toContain(GraphQLDiscovery::class)
            ->and(bootProvider())->toContain(GraphQLDiscovery::class);
    });

    it('scans again after the process cache is forgotten', function () {
        bootProvider();
        skipOnNextScan(GraphQLDiscovery::class);

        expect(bootProvider())->toContain(GraphQLDiscovery::class);

        DiscoveryServiceProvider::forgetProcessCache();
        app()->forgetInstance(DiscoveryCache::class);
        app()->forgetInstance(DiscoveryConfig::class);

        expect(bootProvider())->not->toContain(GraphQLDiscovery::class);
    });

    it('warms once per process, not on every boot', function () {
        $cache = app(DiscoveryCache::class);
        $location = app(DiscoveryConfig::class)->locations[0];

        bootProvider();
        discoveryPool($cache)->deleteItem($location->key);

        bootProvider();

        expect($cache->restore($location))->toBeNull();
    });
});

describe('the file store', function () {
    it('is never warmed on boot, so a deployment decides when it is written', function () {
        config()->set('discovery.cache_store', 'files');
        config()->set('discovery.cache_environments', [app()->environment()]);

        $cache = freshCache();
        $location = app(DiscoveryConfig::class)->locations[0];
        discoveryPool($cache)->deleteItem($location->key);

        bootProvider();

        expect($cache->restore($location))->toBeNull();
    });
});
