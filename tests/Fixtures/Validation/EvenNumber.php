<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class EvenNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value) || $value % 2 !== 0) {
            $fail("The {$attribute} must be an even number.");
        }
    }
}
