<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\StringRule;
use NielsJanssen\Laravel\Validation\TypeRule;

/** An array whose keys are consecutive integers from 0. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ListType extends StringRule implements TypeRule
{
    public string $name {
        get => 'list';
    }
}
