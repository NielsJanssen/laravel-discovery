<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use NielsJanssen\Laravel\Validation\MessageValidationRule;
use NielsJanssen\Laravel\Validation\StringRule;
use NielsJanssen\Laravel\Validation\ValidationContext;
use NielsJanssen\Laravel\Validation\ValidationRule;

/**
 * The escape hatch: any rule Laravel accepts, with no dedicated attribute needed.
 *
 *   #[Rule('min:10')]                                   a rule string
 *   #[Rule(['min:10', 'max:20'])]                       several at once
 *   #[Rule(new ActiveMandate())]                        a ValidationRule object
 *   #[Rule(static fn () => LaravelRule::in($cases))]    a factory, evaluated per validation
 *
 * A closure is always a *factory*: it is called with the ValidationContext and its return
 * value is the rule (or rules). Laravel's own `function ($attribute, $value, $fail)` callback
 * still works — return it from the factory.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Rule implements MessageValidationRule
{
    public function __construct(
        /** @var string|array<int, mixed>|Closure|object */
        public string|array|object $rule,
        public ?string $message = null,
    ) {}

    public function rules(ValidationContext $context): array
    {
        $rule = $this->rule instanceof Closure
            ? ($this->rule)($context)
            : $this->rule;

        $rules = [];

        foreach (is_array($rule) ? $rule : [$rule] as $one) {
            // A wrapped attribute — #[Rule(new Min(5), message: '…')] — contributes its own rules,
            // which is how a named attribute gets a custom message. Anything else, including
            // Laravel's own rule objects, passes through untouched.
            $rules = [
                ...$rules,
                ...$one instanceof ValidationRule ? $one->rules($context) : [$one],
            ];
        }

        return $rules;
    }

    /**
     * The suffix Laravel keys this rule's message under ('min' for 'min:5', or for a wrapped
     * #[Min(5)]), or null when the rule is a foreign object or a factory — those message keys hang
     * off the field path instead.
     */
    public ?string $messageKey {
        get {
            $rule = is_array($this->rule) ? ($this->rule[0] ?? null) : $this->rule;

            if ($rule instanceof StringRule) {
                return $rule->name;
            }

            return is_string($rule) ? explode(':', $rule, 2)[0] : null;
        }
    }
}
