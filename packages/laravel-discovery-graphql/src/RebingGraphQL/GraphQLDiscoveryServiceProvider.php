<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ComposedFromArgsHydrator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\Hydrator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\HydratorRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\LaravelValidationRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\ScalarMap;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapperRegistry;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use RuntimeException;

/**
 * Wires the argument-rules and hydration hooks, and registers the built-in adapters.
 *
 * Tag your own to join them:
 *
 *     $this->app->tag([MyRules::class], RuleProvider::TAG);
 *     $this->app->tag([MyHydrator::class], Hydrator::TAG);
 *     $this->app->tag([MyMapper::class], TypeMapper::TAG);
 *
 * Tagged type mappers are asked before the built-in ScalarMap.
 *
 * Tagging happens in register(), so it is in place before DiscoveryServiceProvider::boot() runs
 * discovery — which needs the hydrators to decide which parameters are hydrated.
 */
final class GraphQLDiscoveryServiceProvider extends ServiceProvider
{
    private const string CONFIG = __DIR__ . '/../../config/discovery-graphql.php';

    public function register(): void
    {
        $this->mergeConfig();

        $this->publishes([
            self::CONFIG => config_path('discovery-graphql.php'),
        ], 'discovery-graphql-config');

        $this->app->tag([ComposedFromArgsHydrator::class], Hydrator::TAG);

        // Our validation package is a suggestion, not a requirement: without it the hook simply has
        // one fewer provider, and #[Arg(rules:)] keeps working. Mirrors how GraphQLDiscovery
        // short-circuits when Rebing's own GraphQL class is absent.
        if (class_exists(RuleCompiler::class)) {
            $this->app->tag([LaravelValidationRules::class], RuleProvider::TAG);
        }

        $this->app->singleton(TypeRegistry::class);

        $this->app->singleton(
            HydratorRegistry::class,
            fn(): HydratorRegistry => new HydratorRegistry(
                $this->tagged(Hydrator::TAG, Hydrator::class),
            ),
        );

        $this->app->singleton(
            TypeMapperRegistry::class,
            fn(): TypeMapperRegistry => new TypeMapperRegistry([
                ...$this->tagged(TypeMapper::TAG, TypeMapper::class),
                $this->app->make(ScalarMap::class),
            ]),
        );

        $this->app->singleton(
            RuleProviderRegistry::class,
            fn(): RuleProviderRegistry => new RuleProviderRegistry(
                $this->tagged(RuleProvider::TAG, RuleProvider::class),
            ),
        );
    }

    /** Fills `discovery.graphql` from the package defaults, a published `config/discovery-graphql.php`, then `discovery.graphql` itself. */
    private function mergeConfig(): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $defaults = require self::CONFIG;
        $published = $config->get('discovery-graphql', []);
        $own = $config->get('discovery.graphql', []);

        $config->set('discovery.graphql', [
            ...(is_array($defaults) ? $defaults : []),
            ...(is_array($published) ? $published : []),
            ...(is_array($own) ? $own : []),
        ]);
    }

    /**
     * Container tags are stringly typed, so a mis-tagged binding would otherwise surface as a
     * confusing error deep inside a resolver. Say so at the point of registration instead.
     *
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return list<T>
     */
    private function tagged(string $tag, string $contract): array
    {
        $tagged = [];

        foreach ($this->app->tagged($tag) as $implementation) {
            if (! $implementation instanceof $contract) {
                throw new RuntimeException(sprintf(
                    '%s is tagged as "%s" but does not implement %s.',
                    get_debug_type($implementation),
                    $tag,
                    $contract,
                ));
            }

            $tagged[] = $implementation;
        }

        return $tagged;
    }
}
