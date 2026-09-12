<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use BackedEnum;
use Illuminate\Validation\Rules\Enum as LaravelEnum;
use LogicException;
use NielsJanssen\Laravel\Validation\NamedRule;
use NielsJanssen\Laravel\Validation\TypeRule;
use NielsJanssen\Laravel\Validation\ValidationContext;

/**
 * The value must be a case of the given enum. Inferred from a backed enum type, so it is only worth
 * writing out to narrow the accepted cases.
 *
 *   #[Enum(Status::class, only: [Status::Draft, Status::Open])]
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Enum extends NamedRule implements TypeRule
{
    /**
     * @param  class-string<BackedEnum>  $class
     * @param  list<BackedEnum>|null  $only  the only cases accepted
     * @param  list<BackedEnum>|null  $except  the cases rejected
     */
    public function __construct(
        public readonly string $class,
        public readonly ?array $only = null,
        public readonly ?array $except = null,
        ?string $message = null,
    ) {
        parent::__construct($message);

        if (! enum_exists($class)) {
            throw new LogicException(sprintf('#[Enum(%s)]: no such enum.', $class));
        }
    }

    /** Laravel looks a Rule-contract object's message up under its own class name. */
    public ?string $messageKey {
        get => LaravelEnum::class;
    }

    public function rules(ValidationContext $context): array
    {
        $rule = new LaravelEnum($this->class);

        if ($this->only !== null) {
            $rule->only($this->only);
        }

        if ($this->except !== null) {
            $rule->except($this->except);
        }

        return [$rule];
    }
}
