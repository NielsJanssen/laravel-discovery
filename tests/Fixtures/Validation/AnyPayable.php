<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Valid;

/** #[Valid] on an interface-typed property, with no narrowing: any implementation goes. */
final class AnyPayable
{
    #[Valid]
    public Payable $method;

    public function __construct(?Payable $method = null)
    {
        $this->method = $method ?? new Cash();
    }
}
