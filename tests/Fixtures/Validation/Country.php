<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Size;

class Country
{
    public function __construct(
        #[Size(2)]
        public string $code = 'NL',
    ) {}
}
