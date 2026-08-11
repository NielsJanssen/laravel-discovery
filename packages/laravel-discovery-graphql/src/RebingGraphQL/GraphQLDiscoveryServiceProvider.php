<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProviderRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\HydratorRegistry;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\RuleProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\LaravelValidationRules;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\Hydrator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ComposedFromArgsHydrator;
use Illuminate\Support\ServiceProvider;
use NielsJanssen\Laravel\Validation\RuleCompiler;
use RuntimeException;

/**
 * Wires the argument-rules and hydration hooks, and registers the built-in adapters.
 *
 * Tag your own to join them:
 *
 *     $this->app->tag([MyRules::class], RuleProvider::TAG);
 *     $this->app->tag([MyHydrator::class], Hydrator::TAG);
 *
 * Tagging happens in register(), so it is in place before DiscoveryServiceProvider::boot() runs
 * discovery — which needs the hydrators to decide which parameters are hydrated.
 */
final class GraphQLDiscoveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([ComposedFromArgsHydrator::class], Hydrator::TAG);

        // Our validation package is a suggestion, not a requirement: without it the hook simply has
        // one fewer provider, and #[Arg(rules:)] keeps working. Mirrors how GraphQLDiscovery
        // short-circuits when Rebing's own GraphQL class is absent.
        if (class_exists(RuleCompiler::class)) {
            $this->app->tag([LaravelValidationRules::class], RuleProvider::TAG);
        }

        $this->app->singleton(
            HydratorRegistry::class,
            fn(): HydratorRegistry => new HydratorRegistry(
                $this->tagged(Hydrator::TAG, Hydrator::class),
            ),
        );

        $this->app->singleton(
            RuleProviderRegistry::class,
            fn(): RuleProviderRegistry => new RuleProviderRegistry(
                $this->tagged(RuleProvider::TAG, RuleProvider::class),
            ),
        );
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
