<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

use Illuminate\Support\Str;

use function class_basename;

/**
 * Base for the named rule attributes (#[Min], #[Email], ...). Each named attribute is a
 * one-line `#[Attribute]` subclass; the rule string is the snake_case of the class name plus
 * the constructor arguments joined as `name:arg1,arg2`. Extend it to add your own — the
 * package finds attributes by interface, so no registration is needed.
 */
abstract class StringRule implements ValidationRule
{
    /** @var list<int|string|float|bool> */
    protected array $parameters;

    public function __construct(int|string|float|bool ...$parameters)
    {
        $this->parameters = array_values($parameters);
    }

    /** The Laravel rule name. Override to derive it differently; see Rule\Type. */
    protected string $name {
        get => Str::snake(class_basename(static::class));
    }

    public function rules(ValidationContext $context): array
    {
        if ($this->parameters === []) {
            return [$this->name];
        }

        return [$this->name . ':' . implode(',', array_map(
            static fn(int|string|float|bool $p): string => match (true) {
                $p === true => 'true',
                $p === false => 'false',
                default => (string) $p,
            },
            $this->parameters,
        ))];
    }
}
