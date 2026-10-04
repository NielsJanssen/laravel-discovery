<?php

declare(strict_types=1);

namespace Benchmarks\Support;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use NielsJanssen\Laravel\Discovery\DiscoveryServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\GraphQLDiscoveryServiceProvider;
use NielsJanssen\Laravel\Validation\ValidationServiceProvider;
use Orchestra\Testbench\Bootstrap\LoadConfiguration as TestbenchLoadConfiguration;
use Rebing\GraphQL\GraphQL;
use Rebing\GraphQL\GraphQLServiceProvider;

final class BenchApp
{
    /** Boots a fresh application for one setup and schema size. */
    public static function create(Setup $setup, Size $size): LaravelApplication
    {
        $providers = $setup->usesDiscovery()
            ? [
                DiscoveryServiceProvider::class,
                ValidationServiceProvider::class,
                GraphQLDiscoveryServiceProvider::class,
                GraphQLServiceProvider::class,
                BenchServiceProvider::class,
            ]
            : [GraphQLServiceProvider::class, BenchServiceProvider::class];

        return Skeleton::create(
            resolvingCallback: static function (LaravelApplication $app) use ($setup, $size): void {
                $app->bind(LoadConfiguration::class, TestbenchLoadConfiguration::class);
                $app->useStoragePath(Paths::storage($size, $setup));
                $app->instance(Target::class, new Target($setup, $size));
            },
            options: [
                'load_environment_variables' => false,
                'extra' => ['providers' => $providers, 'dont-discover' => ['*']],
            ],
        );
    }

    public static function graphql(LaravelApplication $app): GraphQL
    {
        return $app->make(GraphQL::class);
    }

    /**
     * Runs one operation the way Rebing's controller does, minus HTTP.
     *
     * @return array<mixed>
     */
    public static function execute(LaravelApplication $app, string $query): array
    {
        return self::graphql($app)->query($query);
    }
}
