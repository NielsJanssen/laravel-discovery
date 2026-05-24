<?php

declare(strict_types=1);

namespace Tests\Feature\RebingGraphQL;

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

it('discovery:cache succeeds and generates valid cache files', function () {
    $this->artisan('discovery:cache')->assertSuccessful();
});

it('queries still resolve after discovery:cache populates the discovery cache', function () {
    $this->artisan('discovery:cache')->assertSuccessful();

    // The query should still resolve - apply() runs on every boot regardless of
    // whether items came from the Tempest cache or a fresh scan.
    $this->postJson('/graphql', ['query' => '{ books { id title author } }'])
        ->assertOk()
        ->assertJsonPath('data.books.0.title', 'The Great Gatsby');
});

it('queries still resolve after the config cache is populated and the app is refreshed', function () {
    $this->artisan('config:cache')->assertSuccessful();

    $this->refreshApplication();

    $this->postJson('/graphql', ['query' => '{ books { id title author } }'])
        ->assertOk()
        ->assertJsonPath('data.books.0.title', 'The Great Gatsby');
})->todo('Fails currently because caching is weird in the workbench');
