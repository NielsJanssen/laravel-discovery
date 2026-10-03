<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

use Closure;

/**
 * What a RuleProvider implementation contributes for one action: validation rules and their
 * messages, both keyed the way Laravel's validator expects — by GraphQL arg path, not by PHP
 * parameter name.
 */
final readonly class ArgumentRules
{
    /**
     * @param  array<string, mixed>  $rules  keyed by arg path, e.g. 'title' or 'notify.0'
     * @param  array<string, string>  $messages  keyed by arg path and rule, e.g. 'title.min'
     */
    public function __construct(
        public array $rules = [],
        public array $messages = [],
    ) {}

    /**
     * Adds these rules to $rules, accumulating under a path rather than overwriting it.
     *
     * @param  array<string, mixed>  $rules
     * @param  (Closure(string): ?string)|null  $rekey  the key a rule lands under; null drops it
     * @return array<string, mixed>
     */
    public function appendTo(array $rules, ?Closure $rekey = null): array
    {
        foreach ($this->rules as $key => $contributed) {
            $target = $rekey === null ? $key : $rekey((string) $key);

            if ($target === null) {
                continue;
            }

            $existing = $rules[$target] ?? [];

            $rules[$target] = [
                ...is_array($existing) ? $existing : [$existing],
                ...is_array($contributed) ? $contributed : [$contributed],
            ];
        }

        return $rules;
    }

    /**
     * One rule, a pipe-delimited string or a list of rules as a list of rules.
     *
     * @return list<mixed>
     */
    public static function normalise(mixed $rules): array
    {
        return match (true) {
            is_array($rules) => array_values($rules),
            is_string($rules) => explode('|', $rules),
            default => [$rules],
        };
    }

    public function isEmpty(): bool
    {
        return $this->rules === [] && $this->messages === [];
    }
}
