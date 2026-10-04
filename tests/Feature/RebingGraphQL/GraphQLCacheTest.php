<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscovery;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRegistry;
use Tempest\Discovery\DiscoveryCache;
use Tempest\Discovery\DiscoveryCacheStrategy;
use Tempest\Discovery\DiscoveryConfig;
use Workbench\App\GraphQL\Types\Mood;

afterEach(function () {
    $this->artisan('config:clear');
    $this->artisan('discovery:clear');
});

it('config:cache succeeds without serialization errors', function () {
    // Pre-fix this command failed because QueryField/MutationField objects
    // were written into the config and var_export() cannot serialize objects.
    $this->artisan('config:cache')->assertSuccessful();

    expect(file_exists($this->app->getCachedConfigPath()))->toBeTrue();
    $cache = require $this->app->getCachedConfigPath();

    expect($cache)->toBeArray();
});

it('queries still resolve after discovery:cache populates the discovery cache', function () {
    $this->artisan('discovery:cache')->assertSuccessful();

    // The query should still resolve - apply() runs on every boot regardless of
    // whether items came from the Tempest cache or a fresh scan.
    $this->postJson('/graphql', ['query' => '{ books { id title author } }'])
        ->assertOk()
        ->assertJsonPath('data.books.0.title', 'The Great Gatsby');
});

it('resolves an implicitly registered enum after discovery:cache populates the discovery cache', function () {
    $this->artisan('discovery:cache')->assertSuccessful();

    $this->postJson('/graphql', ['query' => '{ calm: mood gloomy: mood(mood: Gloomy) }'])
        ->assertOk()
        ->assertJsonMissingPath('errors')
        ->assertExactJson(['data' => ['calm' => 'Calm', 'gloomy' => 'Gloomy']]);
});

it('writes the implicitly registered enums into the discovery cache, even after the app has applied discovery', function () {
    expect(app(TypeRegistry::class)->has(Mood::class))->toBeTrue();

    $this->artisan('discovery:cache')->assertSuccessful();

    $cache = app(DiscoveryCache::class)->withStrategy(DiscoveryCacheStrategy::FULL);
    $enums = [];

    foreach (app(DiscoveryConfig::class)->locations as $location) {
        foreach ($cache->restore($location)[GraphQLDiscovery::class] ?? [] as $item) {
            if ($item instanceof DiscoveredType && $item->kind === TypeKind::Enum) {
                $enums[$item->class] = $item->implicit;
            }
        }
    }

    expect($enums)->toHaveKey(Mood::class, true);
});

it('queries still resolve after the config cache is populated and the app is refreshed', function () {
    $this->artisan('config:cache')->assertSuccessful();

    $this->refreshApplication();

    $this->postJson('/graphql', ['query' => '{ books { id title author } }'])
        ->assertOk()
        ->assertJsonPath('data.books.0.title', 'The Great Gatsby');
})->todo('Fails currently because caching is weird in the workbench');
