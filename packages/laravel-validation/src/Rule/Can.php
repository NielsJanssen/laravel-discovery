<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\Can as LaravelCan;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The authenticated user must be allowed to perform the ability on the value under validation.
 *
 *   #[Can('update', [Post::class])]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Can extends NamedRule
{
    /**
     * @param  array<int, mixed>  $arguments  extra arguments for the gate, before the value
     */
    public function __construct(
        public readonly string $ability,
        public readonly array $arguments = [],
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    public ?string $messageKey {
        get => LaravelCan::class;
    }

    public function rules(ValidationContext $context): array
    {
        return [new LaravelCan($this->ability, array_values($this->arguments))];
    }
}
