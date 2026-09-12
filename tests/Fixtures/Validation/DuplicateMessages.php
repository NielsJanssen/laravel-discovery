<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;

/** Two messages keyed under `min` would silently overwrite each other. */
final class DuplicateMessages
{
    #[Min(2, message: 'At least two.')]
    #[Min(3, message: 'At least three.')]
    public string $code = 'abcd';
}
