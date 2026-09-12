<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use Illuminate\Validation\Rules\RequiredIf as LaravelRequiredIf;
use Stringable;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class RequiredIf extends ConditionalRule
{
    protected string $resolvedName {
        get => 'required';
    }

    protected function rule(Closure $condition): Stringable
    {
        return new LaravelRequiredIf($condition);
    }
}
