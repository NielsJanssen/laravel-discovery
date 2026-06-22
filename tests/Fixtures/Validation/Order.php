<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Valid;

/** Two levels of nesting: order.customer.country.code */
final class Order
{
    #[Min(3)]
    public string $reference = 'ORD-1';

    #[Valid]
    public Customer $customer;

    public function __construct(?Customer $customer = null)
    {
        $this->customer = $customer ?? new Customer();
    }
}
