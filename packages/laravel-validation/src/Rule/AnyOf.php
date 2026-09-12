<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\AnyOf as LaravelAnyOf;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The value must satisfy at least one of the rule sets.
 *
 *   #[AnyOf([['string', 'email'], ['string', 'ulid']])]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class AnyOf extends NamedRule
{
    /**
     * @param  array<int, mixed>  $ruleSets  one entry per alternative; each a rule or a list of rules
     */
    public function __construct(
        public readonly array $ruleSets,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    public ?string $messageKey {
        get => LaravelAnyOf::class;
    }

    public function rules(ValidationContext $context): array
    {
        return [new LaravelAnyOf(array_values($this->ruleSets))];
    }
}
