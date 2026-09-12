<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use LogicException;
use NielsJanssen\Laravel\Validation\Nesting;

/**
 * Validates every object a member iterates over, each at its own path (`lines.0`, `lines.1`, …).
 *
 *   #[ListOf(OrderLine::class)]               public array $lines;
 *   #[ListOf(Person::class, Company::class)]  public Collection $customers;
 *
 * PHP carries no element type for an array or a Collection, so at least one class is required: a
 * member whose contents are undeclared cannot be written. An element that is not an instance of
 * one of them fails at its own path rather than being validated against whatever class it
 * happens to be. String keys are kept, so a map yields `byRegion.north.code`.
 *
 * Use #[Valid] for a single object, and #[Each] to apply plain rules to every element.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ListOf extends NestingRule
{
    public Nesting $nesting {
        get => Nesting::Each;
    }

    /**
     * @param  class-string  $type
     * @param  class-string  ...$alternatives
     */
    public function __construct(string $type, string ...$alternatives)
    {
        $this->allowedClasses = [$type, ...array_values($alternatives)];

        foreach ($this->allowedClasses as $class) {
            if (! class_exists($class) && ! interface_exists($class)) {
                throw new LogicException(sprintf('#[ListOf(%s)]: no such class or interface.', $class));
            }
        }
    }
}
