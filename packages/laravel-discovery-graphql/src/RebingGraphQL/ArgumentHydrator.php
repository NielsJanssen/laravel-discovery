<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/**
 * Builds a typed object for an action parameter out of the request arguments, so a resolver can take
 * a value object instead of a fistful of scalars.
 *
 * This is the hook for wiring in whichever hydration mechanism you prefer. Ours ships as
 * ComposedFromArgsHydrator, backed by the ComposedFromArgs interface; a spatie/laravel-data adapter
 * would be `hydrates()` → `is_a($class, Data::class, true)` and `hydrate()` → `$class::from($args)`.
 * Register one by tagging it:
 *
 *     $this->app->tag([MyHydrator::class], ArgumentHydrator::TAG);
 *
 * Parameters whose type a hydrator claims are excluded from the generated GraphQL args — the args
 * they are built from come from an ActionArgProvider instead.
 */
interface ArgumentHydrator
{
    public const string TAG = 'graphql.argument_hydrators';

    /**
     * Whether this hydrator builds instances of the given class. Asked at discovery time, so it must
     * decide from the class name alone.
     *
     * @param  class-string  $class
     */
    public function hydrates(string $class): bool;

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $args  the request arguments, keyed by GraphQL arg name
     */
    public function hydrate(string $class, array $args): object;
}
