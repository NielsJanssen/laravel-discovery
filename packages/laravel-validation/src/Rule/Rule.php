<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use LogicException;
use NielsJanssen\Laravel\Validation\MessageValidationRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The escape hatch: any rule Laravel accepts, with no dedicated attribute needed.
 *
 *   #[Rule('min:10')]                                   a rule string
 *   #[Rule(['min:10', 'max:20'])]                       several at once
 *   #[Rule(new ActiveMandate())]                        a ValidationRule object
 *   #[Rule(static function () { return LaravelRule::in($cases); })]  a factory, per validation
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
        public readonly string|array|object $rule,
        public readonly ?string $message = null,
    ) {
        // A message is keyed to one rule name, so it cannot serve a list; stack separate attributes.
        if ($message !== null && is_array($rule) && count($rule) !== 1) {
            throw new LogicException('#[Rule] takes a message for a single rule only; use one #[Rule] per rule.');
        }
    }

    public function rules(ValidationContext $context): array
    {
        $rule = $this->rule instanceof Closure
            ? ($this->rule)($context)
            : $this->rule;

        return is_array($rule) ? array_values($rule) : [$rule];
    }

    /**
     * The suffix Laravel keys this rule's message under ('min' for 'min:5'), or null when the rule
     * is a foreign object or a factory — those message keys hang off the field path instead.
     */
    public ?string $messageKey {
        get {
            $rule = is_array($this->rule) ? ($this->rule[0] ?? null) : $this->rule;

            return is_string($rule) ? explode(':', $rule, 2)[0] : null;
        }
    }
}
