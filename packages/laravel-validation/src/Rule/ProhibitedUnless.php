<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use Illuminate\Validation\Rules\ProhibitedUnless as LaravelProhibitedUnless;
use Stringable;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class ProhibitedUnless extends ConditionalRule
{
    protected string $resolvedName {
        get => 'prohibited';
    }

    protected function rule(Closure $condition): Stringable
    {
        return new LaravelProhibitedUnless($condition);
    }
}
