<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use Stringable;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ExcludeIf extends ConditionalRule
{
    protected function rule(Closure $condition): Stringable
    {
        return new \Illuminate\Validation\Rules\ExcludeIf($condition);
    }
}
