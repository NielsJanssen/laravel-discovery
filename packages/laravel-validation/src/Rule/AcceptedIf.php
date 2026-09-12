<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Closure;
use NielsJanssen\Laravel\Validation\ConditionalString;
use Stringable;

/** Laravel ships no rule object for accepted_if, so the closure form resolves through ConditionalString. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class AcceptedIf extends ConditionalRule
{
    protected string $resolvedName {
        get => 'accepted';
    }

    protected function rule(Closure $condition): Stringable
    {
        return new ConditionalString($condition, 'accepted');
    }
}
