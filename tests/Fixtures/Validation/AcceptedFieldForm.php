<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\AcceptedIf;

final class AcceptedFieldForm
{
    public string $plan = 'pro';

    /** Classic field/value form still produces an `accepted_if:...` string rule. */
    #[AcceptedIf('plan', 'pro')]
    public bool $terms = false;
}
