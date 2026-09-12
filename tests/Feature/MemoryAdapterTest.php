<?php

declare(strict_types=1);

namespace Tests\Feature;

use NielsJanssen\Laravel\Discovery\Cache\MemoryAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

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

    it('drops both the contents and the warm mark when forgotten', function () {
        $pool = MemoryAdapter::forProcess();
        $pool->save($pool->getItem('locations')->set(['a']));
        $pool->markWarm();

        MemoryAdapter::forget();

        expect(MemoryAdapter::forProcess())->not->toBe($pool)
            ->and(MemoryAdapter::forProcess()->isWarm())->toBeFalse()
            ->and(MemoryAdapter::forProcess()->getItem('locations')->isHit())->toBeFalse();
    });
});
