<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\Cache\MemoryAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tempest\Discovery\DiscoveryLocation;

beforeEach(fn() => MemoryAdapter::forget());
afterEach(fn() => MemoryAdapter::forget());

describe('MemoryAdapter', function () {
    it('is a cache pool', function () {
        expect(MemoryAdapter::forProcess())->toBeInstanceOf(ArrayAdapter::class);
    });

    it('hands out one instance for the process', function () {
        expect(MemoryAdapter::forProcess())->toBe(MemoryAdapter::forProcess());
    });

    it('keeps what was stored in it', function () {
        $pool = MemoryAdapter::forProcess();
        $pool->save($pool->getItem('locations')->set(['a', 'b']));

        expect(MemoryAdapter::forProcess()->getItem('locations')->get())->toBe(['a', 'b']);
    });

    it('starts cold and stays warm once marked', function () {
        expect(MemoryAdapter::forProcess()->isWarm())->toBeFalse();

        MemoryAdapter::forProcess()->markWarm();

        expect(MemoryAdapter::forProcess()->isWarm())->toBeTrue();
    });

    it('resolves locations once per autoload path', function () {
        $resolved = 0;
        $resolve = function () use (&$resolved) {
            $resolved++;

            return [new DiscoveryLocation('App\\', __DIR__)];
        };

        $inventory = MemoryAdapter::forProcess()->locations('/srv/inventory', $resolve);

        expect(MemoryAdapter::forProcess()->locations('/srv/inventory', $resolve))->toBe($inventory)
            ->and($resolved)->toBe(1);

        MemoryAdapter::forProcess()->locations('/srv/orders', $resolve);

        expect($resolved)->toBe(2);
    });

    it('drops the contents, the warm mark and the locations when forgotten', function () {
        $pool = MemoryAdapter::forProcess();
        $pool->save($pool->getItem('locations')->set(['a']));
        $pool->locations('/srv/inventory', static fn() => []);
        $pool->markWarm();

        MemoryAdapter::forget();

        expect(MemoryAdapter::forProcess())->not->toBe($pool)
            ->and(MemoryAdapter::forProcess()->isWarm())->toBeFalse()
            ->and(MemoryAdapter::forProcess()->getItem('locations')->isHit())->toBeFalse()
            ->and(MemoryAdapter::forProcess()->locations('/srv/inventory', static fn() => ['fresh']))->toBe(['fresh']);
    });
});
