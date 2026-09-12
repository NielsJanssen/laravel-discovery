<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use BackedEnum;
use DateTimeInterface;

/**
 * Base for the attributes that resolve to a `name:arg1,arg2` rule string (#[Min], #[Between], ...).
 * A subclass declares a typed constructor and returns its arguments from parameters(); a rule with
 * no arguments is an empty class body. Extend it to add your own, since attributes are found by
 * interface and need no registration:
 *
 *   #[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
 *   final class Iban extends StringRule {}                    // "iban"
 */
abstract class StringRule extends NamedRule
{
    /**
     * The rule's arguments, in order. Trailing nulls are dropped, so an optional argument that was
     * not given leaves the rule string shorter rather than emitting an empty parameter.
     *
     * @return list<int|float|string|bool|BackedEnum|DateTimeInterface|null>
     */
    protected function parameters(): array
    {
        return [];
    }

    public function rules(ValidationContext $context): array
    {
        $parameters = array_values(array_filter(
            $this->parameters(),
            static fn(mixed $parameter): bool => $parameter !== null,
        ));

        if ($parameters === []) {
            return [$this->name];
        }

        return [$this->name . ':' . implode(',', array_map($this->format(...), $parameters))];
    }

    private function format(int|float|string|bool|BackedEnum|DateTimeInterface $parameter): string
    {
        return match (true) {
            $parameter === true => 'true',
            $parameter === false => 'false',
            $parameter instanceof BackedEnum => (string) $parameter->value,
            $parameter instanceof DateTimeInterface => $parameter->format('Y-m-d H:i:s'),
            default => (string) $parameter,
        };
    }
}
