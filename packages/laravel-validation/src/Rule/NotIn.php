<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use BackedEnum;
use Illuminate\Validation\Rules\NotIn as LaravelNotIn;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The value must not be one of the given values.
 *
 *   #[NotIn(['nl', 'be'])]
 *   #[NotIn(Status::cases())]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class NotIn extends NamedRule
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

    /** Laravel parses the rule object back to a plain `not_in` rule, so that is where a message is keyed. */
    public ?string $messageKey {
        get => 'not_in';
    }

    public function rules(ValidationContext $context): array
    {
        return [new LaravelNotIn($this->values)];
    }
}
