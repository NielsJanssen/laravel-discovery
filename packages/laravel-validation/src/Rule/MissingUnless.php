<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Support\Arr;
use NielsJanssen\Laravel\Validation\StringRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class MissingUnless extends StringRule
{
    /**
     * @param  string|int|float|bool|array<int, string|int|float|bool>  $values  the other field's value(s)
     */
    public function __construct(
        public readonly string $field,
        public readonly string|int|float|bool|array $values,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    protected function parameters(): array
    {
        return [$this->field, ...array_values(Arr::wrap($this->values))];
    }
}
