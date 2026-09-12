<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use Illuminate\Validation\Rules\ExcludeIf as LaravelExcludeIf;
use Stringable;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ExcludeIf extends ConditionalRule
{
    protected string $resolvedName {
        get => 'exclude';
    }

    protected function rule(Closure $condition): Stringable
    {
        return new LaravelExcludeIf($condition);
    }
}
