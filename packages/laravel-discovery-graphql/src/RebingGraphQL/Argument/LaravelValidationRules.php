<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredAction;
use NielsJanssen\Laravel\Validation\CompiledRules;
use NielsJanssen\Laravel\Validation\RuleCompiler;

/**
 * Bridges nielsjanssen/laravel-validation into the argument-rules hook, so validation attributes
 * work on action parameters:
 *
 *     #[Query(type: 'Book', list: true)]
 *     public function books(#[Min(2), Max(255)] ?string $title = null): array {}
 *
 * Registered only when that package is installed; it is a suggestion, not a requirement. Swap it for
 * an adapter over any other library by tagging your own RuleProvider implementation.
 */
final class LaravelValidationRules implements RuleProvider
{
    public function __construct(
        private readonly RuleCompiler $compiler,
    ) {}

    public function rulesFor(DiscoveredAction $action, array $args): ArgumentRules
    {
        $rules = [];
        $messages = [];

        // The action's own parameters, whose plan is keyed by parameter name. The `Class::method`
        // string is the name a cached plan is stored under, so no reflection is needed per request.
        $this->collect($this->compiler->forValues($action->class . '::' . $action->method, $action->toParameters($args)), $action, $rules, $messages);

        // Hydrated value objects: their properties are named after the flat args that feed them
        // (Pagination's $page/$limit against #[Paginated]'s page/limit args), so their rules key
        // straight onto those arg names with no prefix.
        foreach ($action->argCompositions as $valueObjectClass) {
            $this->collect($this->compiler->forValues($valueObjectClass, $args), $action, $rules, $messages);
        }

        return new ArgumentRules($rules, $messages);
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @param  array<string, string>  $messages
     */
    private function collect(CompiledRules $compiled, DiscoveredAction $action, array &$rules, array &$messages): void
    {
        foreach ($compiled->rules as $path => $pathRules) {
            $argPath = $action->toArgPath($path);

            $rules[$argPath] = [...$rules[$argPath] ?? [], ...$pathRules];
        }

        foreach ($compiled->messages as $path => $message) {
            // Message keys are `path.rule`, so the rule suffix has to survive the translation.
            $messages[$action->toArgPath($path)] = $message;
        }
    }
}
