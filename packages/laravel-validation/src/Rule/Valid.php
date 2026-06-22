<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use NielsJanssen\Laravel\Validation\Nesting;

/**
 * Validates the single object a member holds. Validation is opt-in, so a nested object is only
 * recursed into when it carries this attribute.
 *
 *   #[Valid] public Address $address;                       // allowed: Address and subclasses
 *   #[Valid(Ltd::class, Plc::class)] public Company $c;     // narrows an abstract declared type
 *
 * No argument is needed: the declared type already says what is allowed, and PHP enforces it.
 * Passing classes narrows that to specific implementations of an abstract or interface type.
 *
 * Use #[ListOf] or #[Each] for a member holding many elements.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Valid extends NestingRule
{
    public Nesting $nesting {
        get => Nesting::Value;
    }

    /**
     * @param  class-string  ...$allowed
     */
    public function __construct(string ...$allowed)
    {
        $this->allowedClasses = array_values($allowed);
    }
}
