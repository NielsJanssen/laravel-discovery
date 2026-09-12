<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;

/**
 * The registered RuleProvider implementations, merged into one rule set per action.
 */
final class RuleProviderRegistry
{
    public function __construct(
        /** @var iterable<RuleProvider> lazy, since Container::tagged() returns a generator */
        private readonly iterable $providers = [],
    ) {}

    /**
     * Every provider contributes; rules for the same arg accumulate rather than overwrite, so
     * several libraries can validate the same field.
     *
     * @param  array<string, mixed>  $args
     */
    public function rulesFor(DiscoveredAction $action, array $args): ArgumentRules
    {
        $rules = [];
        $messages = [];

        foreach ($this->providers as $provider) {
            $set = $provider->rulesFor($action, $args);

            foreach ($set->rules as $path => $contributed) {
                $rules[$path] = [
                    ...$rules[$path] ?? [],
                    ...is_array($contributed) ? $contributed : [$contributed],
                ];
            }

            $messages = [...$messages, ...$set->messages];
        }

        return new ArgumentRules($rules, $messages);
    }
}
