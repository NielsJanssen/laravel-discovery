<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Closure;
use Illuminate\Support\Arr;
use NielsJanssen\Laravel\Validation\StringRule;
use NielsJanssen\Laravel\Validation\ValidationContext;
use Stringable;

/**
 * Base for Laravel's conditional rules (required_if, prohibited_unless, ...). Each takes either a
 * closure or the classic field-and-values form:
 *
 *   #[RequiredIf(static function (ValidationContext $c): bool { return $c->root->subscribe; })]
 *   #[RequiredIf('plan', 'pro')]                  // required_if:plan,pro
 *   #[RequiredIf('plan', ['pro', 'team'])]        // required_if:plan,pro,team
 *
 * Laravel invokes a rule's condition closure with no arguments, so the context is bound in at
 * resolve time.
 */
abstract class ConditionalRule extends StringRule
{
    /** @var list<string|int|float|bool> */
    private readonly array $values;

    /**
     * @param  Closure(ValidationContext): bool|string  $condition  a closure, or the other field's name
     * @param  string|int|float|bool|array<int, string|int|float|bool>|null  $values  the other field's value(s), for the field form
     */
    public function __construct(
        public readonly Closure|string $condition,
        string|int|float|bool|array|null $values = null,
        ?string $message = null,
    ) {
        parent::__construct($message);

        $this->values = $values === null ? [] : array_values(Arr::wrap($values));
    }

    /**
     * The closure form resolves to a rule object that Laravel parses back to its unconditional name
     * (`required`, `prohibited`, ...), so a message has to be keyed there.
     */
    public ?string $messageKey {
        get => $this->condition instanceof Closure ? $this->resolvedName : $this->name;
    }

    /** What the closure form's rule object reads as once Laravel has parsed it. */
    abstract protected string $resolvedName { get; }

    protected function parameters(): array
    {
        assert(is_string($this->condition));

        return [$this->condition, ...$this->values];
    }

    public function rules(ValidationContext $context): array
    {
        if (! $this->condition instanceof Closure) {
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
