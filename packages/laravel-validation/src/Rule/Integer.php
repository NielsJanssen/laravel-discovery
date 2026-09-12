<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;
use NielsJanssen\Laravel\Validation\TypeRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Integer extends StringRule implements TypeRule
{
    /**
     * @param  bool  $strict  accept only a real integer, not a string or a number that looks like one
     */
    public function __construct(
        public readonly bool $strict = false,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    protected function parameters(): array
    {
        return $this->strict ? ['strict'] : [];
    }
}
