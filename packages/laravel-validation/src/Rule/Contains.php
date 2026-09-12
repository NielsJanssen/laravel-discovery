<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use BackedEnum;
use Illuminate\Validation\Rules\Contains as LaravelContains;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The array under validation must contain all of the given values.
 *
 *   #[Contains(['nl', 'be'])]
 *   #[Contains(Status::cases())]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Contains extends NamedRule
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

    /** Laravel parses the rule object back to a plain `contains` rule, so that is where a message is keyed. */
    public ?string $messageKey {
        get => 'contains';
    }

    public function rules(ValidationContext $context): array
    {
        return [new LaravelContains($this->values)];
    }
}
