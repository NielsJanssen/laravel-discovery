<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;

final class PartialForm
{
    #[Min(5)]
    public string $name = 'Niels Janssen';

    /** No attribute → not validated, even though it would fail `min:5`. */
    public string $note = 'x';

    public ?int $internal = null;
}
