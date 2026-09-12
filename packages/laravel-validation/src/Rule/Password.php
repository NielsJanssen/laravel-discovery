<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\Password as LaravelPassword;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 *   #[Password(min: 12, letters: true, numbers: true, uncompromised: 0)]
 *
 * `uncompromised` is the number of times the password may appear in a breach database before it is
 * rejected, so 0 rejects anything that has ever leaked.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Password extends NamedRule
{
    public function __construct(
        public readonly int $min = 8,
        public readonly bool $letters = false,
        public readonly bool $mixedCase = false,
        public readonly bool $numbers = false,
        public readonly bool $symbols = false,
        public readonly ?int $uncompromised = null,
        public readonly ?int $max = null,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    public ?string $messageKey {
        get => LaravelPassword::class;
    }

    public function rules(ValidationContext $context): array
    {
        $rule = new LaravelPassword($this->min);

        if ($this->letters) {
            $rule->letters();
        }

        if ($this->mixedCase) {
            $rule->mixedCase();
        }

        if ($this->numbers) {
            $rule->numbers();
        }

        if ($this->symbols) {
            $rule->symbols();
        }

        if ($this->uncompromised !== null) {
            $rule->uncompromised($this->uncompromised);
        }

        if ($this->max !== null) {
            $rule->max($this->max);
        }

        return [$rule];
    }
}
