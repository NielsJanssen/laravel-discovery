<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Valid;

final class Customer
{
    #[Min(2)]
    public string $name = 'Anouk';

    #[Valid]
    public Country $country;

    /** Class-typed but unmarked: must not be recursed into. */
    public Country $billingCountry;

    public function __construct(?Country $country = null)
    {
        $this->country = $country ?? new Country();
        $this->billingCountry = new Country('INVALID');
    }
}
