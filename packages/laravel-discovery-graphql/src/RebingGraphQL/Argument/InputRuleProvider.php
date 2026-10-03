<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

/** Contributes validation rules and messages for the properties of an #[Input] class. */
interface InputRuleProvider
{
    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $values  one input object's values, keyed by property name
     * @return ArgumentRules  rules keyed by property name, messages by property name and rule, e.g. 'title.min'
     */
    public function rulesForInput(string $class, array $values): ArgumentRules;
}
