<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use BackedEnum;
use Illuminate\Validation\Rules\In as LaravelIn;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The value must be one of the given values. The rule object quotes them, so a value holding a comma survives.
 *
 *   #[In(['nl', 'be'])]
 *   #[In(Status::cases())]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class In extends NamedRule
{
    /**
     * @param  list<string|int|float|BackedEnum>  $values
     */
    public function __construct(
        public readonly array $values,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    /** Laravel parses the rule object back to a plain `in` rule, so that is where a message is keyed. */
    public ?string $messageKey {
        get => 'in';
    }

    public function rules(ValidationContext $context): array
    {
        return [new LaravelIn($this->values)];
    }
}
