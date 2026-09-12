<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\Email as LaravelEmail;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * A plain #[Email] is Laravel's `email` rule. Each flag turns on one of the stricter checks and
 * switches the attribute over to Laravel's Email rule object.
 *
 *   #[Email]
 *   #[Email(strict: true, dns: true)]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Email extends NamedRule
{
    /**
     * @param  bool  $strict  RFC compliant with no warnings
     * @param  bool  $dns  the domain must have an MX record
     * @param  bool  $spoof  reject homograph and invisible characters
     * @param  bool  $native  validate with PHP's own filter_var()
     * @param  bool  $unicode  allow unicode characters, with $native
     */
    public function __construct(
        public readonly bool $strict = false,
        public readonly bool $dns = false,
        public readonly bool $spoof = false,
        public readonly bool $native = false,
        public readonly bool $unicode = false,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    /** The rule object is Rule-contract backed, so Laravel keys its message under the class name. */
    public ?string $messageKey {
        get => $this->isPlain() ? 'email' : LaravelEmail::class;
    }

    public function rules(ValidationContext $context): array
    {
        if ($this->isPlain()) {
            return ['email'];
        }

        $rule = new LaravelEmail();

        $rule->rfcCompliant($this->strict);

        if ($this->dns) {
            $rule->validateMxRecord();
        }

        if ($this->spoof) {
            $rule->preventSpoofing();
        }

        if ($this->native || $this->unicode) {
            $rule->withNativeValidation($this->unicode);
        }

        return [$rule];
    }

    private function isPlain(): bool
    {
        return ! $this->strict && ! $this->dns && ! $this->spoof && ! $this->native && ! $this->unicode;
    }
}
