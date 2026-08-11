<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

/**
 * Contributes validation rules for a discovered action's arguments.
 *
 * This is the hook for wiring in whichever validation library you prefer: ours ships as
 * LaravelValidationRules, but a spatie/laravel-data adapter — or anything else — plugs in on equal
 * footing. Register an implementation by tagging it:
 *
 *     $this->app->tag([MyRules::class], RuleProvider::TAG);
 *
 * Every tagged provider is asked, and their rules merge, so several can coexist. Rules contributed
 * here are merged *on top of* Rebing's own arg-level rules (including #[Arg(rules:)] and the
 * model-binding `exists` rule) rather than replacing them.
 */
interface RuleProvider
{
    public const string TAG = 'graphql.argument_rules';

    /**
     * @param  array<string, mixed>  $args  the request arguments, keyed by GraphQL arg name
     */
    public function rulesFor(DiscoveredAction $action, array $args): RuleSet;
}
