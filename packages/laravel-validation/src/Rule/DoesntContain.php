<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use BackedEnum;
use Illuminate\Validation\Rules\DoesntContain as LaravelDoesntContain;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The array under validation must contain none of the given values.
 *
 *   #[DoesntContain(['nl', 'be'])]
 *   #[DoesntContain(Status::cases())]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class DoesntContain extends NamedRule
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

    /** Laravel parses the rule object back to a plain `doesnt_contain` rule, so that is where a message is keyed. */
    public ?string $messageKey {
        get => 'doesnt_contain';
    }

    public function rules(ValidationContext $context): array
    {
        return [new LaravelDoesntContain($this->values)];
    }
}
