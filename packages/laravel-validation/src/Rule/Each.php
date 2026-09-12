<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use LogicException;
use NielsJanssen\Laravel\Validation\Nesting;
use NielsJanssen\Laravel\Validation\ValidationRule;

/**
 * Applies rules to every element a member iterates over, each at its own path.
 *
 *   #[Each('email')]                                  public array $recipients;
 *   #[Each('integer', 'between:1,10')]                public array $scores;
 *   #[Each(new Min(2, message: 'Too short.'))]        public array $tags;
 *
 * Composes with #[ListOf], which validates the elements as nested objects; #[Each] then adds
 * rules on the element itself.
 *
 * Element rules are stored in the cached plan rather than re-read through reflection, so unlike a
 * property's own rules they cannot hold a closure — put the factory on the element's own property,
 * or pass a rule object.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Each extends NestingRule
{
    public Nesting $nesting {
        get => Nesting::Each;
    }

    /**
     * @param  string|array<int, mixed>|object  $rule
     * @param  string|array<int, mixed>|object  ...$more
     */
    public function __construct(string|array|object $rule, string|array|object ...$more)
    {
        foreach ([$rule, ...array_values($more)] as $argument) {
            // Country::class and 'email' are both strings; a loadable class here is almost
            // certainly the wrong attribute rather than a rule named after a class.
            if (is_string($argument) && (class_exists($argument) || interface_exists($argument))) {
                throw new LogicException(sprintf(
                    '#[Each(%s)]: that is a class. Use #[ListOf(%s)] to validate every element '
                    . 'as a nested object.',
                    $argument,
                    $argument,
                ));
            }

            $this->elementRules[] = $argument instanceof ValidationRule ? $argument : new Rule($argument);
        }
    }
}
