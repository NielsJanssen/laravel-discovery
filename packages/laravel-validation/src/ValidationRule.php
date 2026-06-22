<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * Implemented by any property attribute that contributes Laravel validation rules.
 * Discovered by interface (ReflectionAttribute::IS_INSTANCEOF), so user-defined
 * attributes are picked up without registration.
 */
interface ValidationRule
{
    /**
     * The Laravel rules this attribute contributes for its property.
     *
     * @return array<int, mixed>
     */
    public function rules(ValidationContext $context): array;
}
