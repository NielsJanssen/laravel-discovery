<?php

declare(strict_types=1);

namespace Tests;

use Livewire\LivewireServiceProvider;
use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscoveryServiceProvider;
use NielsJanssen\Laravel\Validation\ValidationServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Rebing\GraphQL\GraphQLServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Parallel workers share the testbench skeleton, so each writes its config cache to its own file.
        if ($token = $this->parallelToken()) {
            $_SERVER['APP_CONFIG_CACHE'] = $_ENV['APP_CONFIG_CACHE'] = "bootstrap/cache/config-{$token}.php";
        }

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            DiscoveryServiceProvider::class,
            ValidationServiceProvider::class,
            GraphQLDiscoveryServiceProvider::class,
            GraphQLServiceProvider::class,
            LivewireServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Livewire full-page routes run in the `web` group, whose cookie
        // encryption needs an application key.
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));

        // Full-page Livewire components render into a layout. Point it at a
        // plain workbench view so we don't depend on Livewire's `layouts::`
        // view namespace being registered.
        $app['config']->set('livewire.component_layout', 'layouts.app');

        // The test app runs on the testbench skeleton, so the workbench's
        // view directory is not on the view path by default. Add it so the
        // Livewire layout above resolves.
        $app['config']->set('view.paths', array_merge(
            [dirname(__DIR__) . '/workbench/resources/views'],
            $app['config']->get('view.paths', []),
        ));

        if ($token = $this->parallelToken()) {
            $app['config']->set('discovery.cache_path', "framework/cache/discovery-{$token}");
        }
    }

    /** The paratest worker token, or null when the suite runs serially. */
    private function parallelToken(): ?string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
