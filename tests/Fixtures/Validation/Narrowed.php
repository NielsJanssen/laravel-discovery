<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Valid;

interface Payable {}

final class Invoice implements Payable
{
    public function __construct(
        #[Min(3)]
        public string $number = 'INV-1',
    ) {}
}

final class Cash implements Payable
{
    public function __construct(
        #[Min(3)]
        public string $till = 'TILL-1',
    ) {}
}

/** #[Valid] narrowed to specific implementations of an interface. */
final class Narrowed
{
    #[Valid(Invoice::class)]
    public Payable $method;

    public function __construct(?Payable $method = null)
    {
        $this->method = $method ?? new Invoice();
    }
}
