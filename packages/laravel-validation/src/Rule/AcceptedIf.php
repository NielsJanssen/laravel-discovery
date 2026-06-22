<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use Stringable;
use NielsJanssen\Laravel\Validation\ConditionalString;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class AcceptedIf extends ConditionalRule
{
    protected function rule(Closure $condition): Stringable
    {
        return new ConditionalString($condition, 'accepted');
    }
}
