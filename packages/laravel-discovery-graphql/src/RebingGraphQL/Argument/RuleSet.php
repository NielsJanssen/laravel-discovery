<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

/**
 * What an RuleProvider implementation contributes for one action: validation rules and their
 * messages, both keyed the way Laravel's validator expects — by GraphQL arg path, not by PHP
 * parameter name.
 */
final readonly class RuleSet
{
    /**
     * @param  array<string, mixed>  $rules  keyed by arg path, e.g. 'title' or 'notify.0'
     * @param  array<string, string>  $messages  keyed by arg path and rule, e.g. 'title.min'
     */
    public function __construct(
        public array $rules = [],
        public array $messages = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->rules === [] && $this->messages === [];
    }
}
