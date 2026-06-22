<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use Closure;
use Stringable;

/**
 * A Stringable rule that resolves to a target rule (for example "accepted") when its
 * condition holds, and to an empty string when it does not. This mirrors how Laravel's own
 * RequiredIf rule resolves to "required" or "", and backs the package-provided conditional
 * attributes (#[AcceptedIf], #[DeclinedIf]) that Laravel offers no native closure object for.
 */
final class ConditionalString implements Stringable
{
    public function __construct(
        private readonly Closure $condition,
        private readonly string $rule,
    ) {}

    public function __toString(): string
    {
        return ($this->condition)() ? $this->rule : '';
    }
}
