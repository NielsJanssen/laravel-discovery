<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class DigitsBetween extends StringRule
{
    public function __construct(
        public readonly int $min,
        public readonly int $max,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    protected function parameters(): array
    {
        return [$this->min, $this->max];
    }
}
