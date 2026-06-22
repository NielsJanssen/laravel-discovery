<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * A validation attribute that makes the compiler descend into what a member holds. The built-in
 * ones are #[Valid], #[ListOf] and #[Each]; implement this to add your own — RuleFinder looks for
 * the interface, never for a concrete class.
 *
 * Several may sit on one member as long as they agree on `$nesting`: #[ListOf] and #[Each] compose
 * because both describe elements, while #[Valid] cannot join them because it describes one object.
 */
interface NestedValidationRule extends ValidationRule
{
    /** Whether the member holds one object or many elements. */
    public Nesting $nesting { get; }

    /**
     * Classes the member may hold. Empty means "no constraint from this attribute"; for
     * Nesting::Value the declared type is used instead.
     *
     * @var list<class-string>
     */
    public array $allowedClasses { get; }

    /**
     * Rules applied to every element in its own right.
     *
     * @var list<ValidationRule>
     */
    public array $elementRules { get; }
}
