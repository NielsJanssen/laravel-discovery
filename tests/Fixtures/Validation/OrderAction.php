<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Between;
use NielsJanssen\Laravel\Validation\Rule\Min;

final class OrderAction
{
    public function place(
        #[Min(1)]
        string $sku,
        #[Between(1, 999)]
        int $quantity,
        ?string $note = null,   // no attribute → not validated
    ): void {}
}
