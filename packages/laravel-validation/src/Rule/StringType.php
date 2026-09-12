<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;
use NielsJanssen\Laravel\Validation\TypeRule;

/** `string` is a reserved word, so the attribute carries the Type suffix and the rule name does not. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class StringType extends StringRule implements TypeRule
{
    public string $name {
        get => 'string';
    }
}
