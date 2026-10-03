<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Rejects an explicit null for an Omitted field whose PHP type takes no null. */
final class NotNull implements ValidationRule
{
    public const string MESSAGE = 'The :attribute field may be left out, but not set to null.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            $fail(self::MESSAGE);
        }
    }
}
