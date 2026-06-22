<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Nullable;
use NielsJanssen\Laravel\Validation\Rule\NumericType;
use NielsJanssen\Laravel\Validation\Rule\StringType;

final class InferenceForm
{
    /** Writing the inferred rule out by hand must not double it. */
    #[Nullable, Min(2)]
    public ?string $explicitNullable = null;

    #[StringType, Min(2)]
    public string $explicitString = 'abc';

    #[NumericType]
    public int $overridden = 5;

    /** Nothing sane to infer for a union; the attribute stands alone. */
    #[Min(2)]
    public string|int $union = 'abc';

    public function optional(
        #[Min(2)]
        string $required,
        #[Min(2)]
        string $defaulted = 'abc',
        #[Min(2)]
        ?string $nullableDefaulted = null,
    ): void {}
}
