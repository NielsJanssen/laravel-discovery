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

        foreach ($action->flattenedInputs as $flattened) {
            $set = $this->rulesForInput($flattened->type->class, $flattened->toProperties($args));

            foreach ($set->rules as $property => $contributed) {
                if ($flattened->skipsRulesOf($property, $args)) {
                    continue;
                }

                $path = $flattened->toArgPath($property);

                $rules[$path] = [
                    ...$rules[$path] ?? [],
                    ...is_array($contributed) ? $contributed : [$contributed],
                ];
            }

            foreach ($set->messages as $key => $message) {
                $messages[$flattened->toArgPath($key)] = $message;
            }
        }

        return new ArgumentRules($rules, $messages);
    }

    /**
     * The rules and messages every InputRuleProvider contributes for one input object, accumulated per property.
     *
     * @param  class-string  $class
     * @param  array<string, mixed>  $values  keyed by property name
     */
    public function rulesForInput(string $class, array $values): ArgumentRules
    {
        $rules = [];
        $messages = [];

        foreach ($this->providers as $provider) {
            if (! $provider instanceof InputRuleProvider) {
                continue;
            }

            $set = $provider->rulesForInput($class, $values);

            foreach ($set->rules as $property => $contributed) {
                $rules[$property] = [
                    ...$rules[$property] ?? [],
                    ...is_array($contributed) ? $contributed : [$contributed],
                ];
            }

            $messages = [...$messages, ...$set->messages];
        }

        return new ArgumentRules($rules, $messages);
    }
}
