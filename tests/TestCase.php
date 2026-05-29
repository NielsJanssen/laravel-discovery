<?php

declare(strict_types=1);

namespace Tests;

use Livewire\LivewireServiceProvider;
use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Rebing\GraphQL\GraphQLServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DiscoveryServiceProvider::class,
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
    }
}
