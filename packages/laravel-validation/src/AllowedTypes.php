<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule as LaravelRule;

use function class_basename;

/**
 * Guards what a #[Valid] member may hold. A collection carries no item type of its own, so
 * without this the contents would decide their own contract: drop a Country into a
 * `Collection $continents` and it would be happily validated as a Country.
 */
final readonly class AllowedTypes implements LaravelRule
{
    /**
     * @param  list<class-string>  $allowed
     */
    public function __construct(
        public array $allowed,
    ) {}

    public function accepts(mixed $value): bool
    {
        foreach ($this->allowed as $class) {
            if ($value instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->accepts($value)) {
            return;
        }

        $fail(sprintf(
            'The :attribute must be %s, %s given.',
            implode(' or ', array_map(static fn(string $c): string => class_basename($c), $this->allowed)),
            is_object($value) ? class_basename($value) : get_debug_type($value),
        ));
    }
}
