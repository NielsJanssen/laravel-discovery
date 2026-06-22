<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Closure;
use NielsJanssen\Laravel\Validation\StringRule;
use NielsJanssen\Laravel\Validation\ValidationContext;
use Stringable;

/**
 * Base for Laravel's conditional rules (required_if, prohibited_unless, ...). Each takes
 * either a closure or the classic field/value arguments:
 *
 *   #[RequiredIf(static fn (ValidationContext $c): bool => $c->root->subscribe)]
 *   #[RequiredIf('other_field', 'value')]          // string form: required_if:other_field,value
 *
 * The string form is just a StringRule, so it inherits that formatting. Laravel invokes a
 * rule's condition closure with no arguments, so the context is bound in here at resolve time.
 */
abstract class ConditionalRule extends StringRule
{
    private readonly ?Closure $condition;

    public function __construct(Closure|string $key, int|string|float|bool ...$arguments)
    {
        if ($key instanceof Closure) {
            parent::__construct();
            $this->condition = $key;
        } else {
            parent::__construct($key, ...$arguments);
            $this->condition = null;
        }
    }

    public function rules(ValidationContext $context): array
    {
        if ($this->condition === null) {
            return parent::rules($context);
        }

        $condition = $this->condition;

        return [$this->rule(static fn(): bool => (bool) $condition($context))];
    }

    /**
     * Build the Laravel rule object from a no-argument condition closure.
     */
    abstract protected function rule(Closure $condition): Stringable;
}
