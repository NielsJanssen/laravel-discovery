<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;

final class Address
{
    public function __construct(
        #[Min(5)]
        public string $street = '',
    ) {}
}
