<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\Cache\MemoryAdapter;
use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\Testing\WithCachedDiscovery;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;
use Tempest\Discovery\DiscoveryLocation;

uses(WithCachedDiscovery::class);

afterEach(fn() => MemoryAdapter::forget());

/**
 * Boot discovery with GraphQLDiscovery skipped. Skipping only takes effect while scanning, so a boot that still
 * reports it restored the cache instead.
 *
 * @return list<string>
 */
function bootSkippingGraphQL(): array
{
    config()->set('discovery.skip_classes', [GraphQLDiscovery::class]);
    app()->forgetInstance(DiscoveryConfig::class);
    app()->forgetInstance(DiscoveryCache::class);

    new DiscoveryServiceProvider(app())->boot();

    return config('discovery.discovery_classes');
}

describe('WithCachedDiscovery', function () {
    it('warms the process cache on set up', function () {
        expect(MemoryAdapter::forProcess()->isWarm())->toBeTrue();
    });

    it('stores every location from the boot that scanned', function () {
        $cache = new DiscoveryCache(DiscoveryCacheStrategy::FULL, MemoryAdapter::forProcess());

        foreach (app(DiscoveryConfig::class)->locations as $location) {
            expect($cache->restore($location))->not->toBeNull();
        }
    });

    it('caches later boots outside cache_environments', function () {
        $this->refreshApplication();

        expect(app()->environment())->not->toBeIn(config('discovery.cache_environments'))
            ->and(app(DiscoveryCache::class)->strategy)->toBe(DiscoveryCacheStrategy::FULL);
    });

    it('restores on a later boot instead of scanning', function () {
        $this->refreshApplication();

        expect(bootSkippingGraphQL())->toContain(GraphQLDiscovery::class);
    });

    it('reads composer once for every later boot', function () {
        $this->refreshApplication();
        $first = app(DiscoveryConfig::class)->locations;

        $this->refreshApplication();
        $second = app(DiscoveryConfig::class)->locations;

        expect($second[0])->toBe($first[0]);
    });

    it('lets a provider add locations on every boot without duplicating them', function () {
        $this->refreshApplication();
        $this->refreshApplication();

        $paths = array_map(static fn(DiscoveryLocation $location) => $location->path, app(DiscoveryConfig::class)->locations);

        expect($paths)->toBe(array_values(array_unique($paths)))
            ->and(bootSkippingGraphQL())->toContain(GraphQLDiscovery::class);
    });

    it('scans again after the process cache is forgotten', function () {
        MemoryAdapter::forget();

        expect(bootSkippingGraphQL())->not->toContain(GraphQLDiscovery::class);
    });
});
