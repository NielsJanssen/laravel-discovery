<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\ServiceProvider;
use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;

describe('config publishing', function () {
    it('registers a discovery-config publish tag pointing at the package config', function () {
        $paths = ServiceProvider::pathsToPublish(DiscoveryServiceProvider::class, 'discovery-config');

        expect($paths)->toHaveCount(1);

        $expectedSource = realpath(__DIR__ . '/../../packages/laravel-discovery/config/discovery.php');
        $expectedTarget = config_path('discovery.php');

        $source = array_key_first($paths);

        expect(realpath($source))->toBe($expectedSource);
        expect($paths[$source])->toBe($expectedTarget);
    });
});
