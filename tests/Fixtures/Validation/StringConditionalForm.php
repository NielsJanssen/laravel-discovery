<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\RequiredUnless;

final class StringConditionalForm
{
    public string $subscribe = '1';

    /** Classic field/value form still produces a `required_unless:...` string rule. */
    #[RequiredUnless('subscribe', '1')]
    public ?string $reason = null;
}
