<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Confirmed extends StringRule
{
    /**
     * @param  string|null  $field  the field holding the confirmation; `<name>_confirmation` by default
     */
    public function __construct(
        public readonly ?string $field = null,
        ?string $message = null,
    ) {
        parent::__construct($message);
    }

    protected function parameters(): array
    {
        return [$this->field];
    }
}
