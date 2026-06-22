<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Valid;

final class ValidOnIterable
{
    #[Valid]
    public array $things = [];
}
