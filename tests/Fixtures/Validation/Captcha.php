<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use Attribute;
use NielsJanssen\Laravel\Validation\ValidationContext;
use NielsJanssen\Laravel\Validation\ValidationRule;

/** A user-defined attribute: works because it implements the interface, with no registration. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER | Attribute::IS_REPEATABLE)]
final class Captcha implements ValidationRule
{
    public function rules(ValidationContext $context): array
    {
        return ['string', 'size:6', 'alpha_num'];
    }
}
