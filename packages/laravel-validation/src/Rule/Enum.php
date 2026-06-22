<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation\Rule;

use Attribute;
use Illuminate\Validation\Rules\Enum as LaravelEnum;
use NielsJanssen\Laravel\Validation\ValidationContext;
use NielsJanssen\Laravel\Validation\ValidationRule;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
class Enum implements ValidationRule
{
    public function __construct(
        /** @var class-string<\BackedEnum> */
        public string $enumClass,
    ) {}

    public function rules(ValidationContext $context): array
    {
        return [
            new LaravelEnum($this->enumClass),
        ];
    }
}
