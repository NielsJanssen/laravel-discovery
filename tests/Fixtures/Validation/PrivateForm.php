<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Email;
use NielsJanssen\Laravel\Validation\Rule\Min;

final class PrivateForm
{
    /** Plain private property with a rule attribute. */
    #[Min(3)]
    private string $code = 'ABCD';

    public function __construct(
        // Constructor-promoted private readonly properties (typical value object).
        #[Min(5)]
        private readonly string $name = 'Niels Janssen',
        #[Email]
        private readonly ?string $email = null,
    ) {}
}
