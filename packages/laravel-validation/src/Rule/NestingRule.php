<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use NielsJanssen\Laravel\Validation\NestedValidationRule;
use NielsJanssen\Laravel\Validation\ValidationContext;
use NielsJanssen\Laravel\Validation\ValidationRule;

/**
 * Shared body for the nesting attributes: they contribute structure rather than rules of their
 * own, and each overrides only the one thing it actually carries. Extending this is a convenience;
 * implementing NestedValidationRule directly works just as well, since that is what RuleFinder looks for.
 */
abstract class NestingRule implements NestedValidationRule
{
    /** @var list<class-string> */
    public array $allowedClasses = [];

    /** @var list<ValidationRule> */
    public array $elementRules = [];

    public function rules(ValidationContext $context): array
    {
        return [];
    }
}
