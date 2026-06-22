<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Required;
use NielsJanssen\Laravel\Validation\Rule\Valid;

/** #[Valid] must compose with the member's own rules. */
final class RequiredNested
{
    #[Required, Valid]
    public ?Country $country = null;
}
