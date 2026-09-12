<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\Between;
use NielsJanssen\Laravel\Validation\Rule\Decimal;
use NielsJanssen\Laravel\Validation\Rule\Digits;
use NielsJanssen\Laravel\Validation\Rule\DigitsBetween;
use NielsJanssen\Laravel\Validation\Rule\Max;
use NielsJanssen\Laravel\Validation\Rule\MaxDigits;
use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\MinDigits;
use NielsJanssen\Laravel\Validation\Rule\MultipleOf;
use NielsJanssen\Laravel\Validation\Rule\Size;

final class SizeRules
{
    #[Min(5)]
    public mixed $min = null;

    #[Max(10.5)]
    public mixed $max = null;

    #[Size(6)]
    public mixed $size = null;

    #[Between(1, 10)]
    public mixed $between = null;

    #[Digits(4)]
    public mixed $digits = null;

    #[MinDigits(2)]
    public mixed $minDigits = null;

    #[MaxDigits(8)]
    public mixed $maxDigits = null;

    #[DigitsBetween(2, 8)]
    public mixed $digitsBetween = null;

    /** The optional maximum is dropped rather than emitted empty. */
    #[Decimal(2)]
    public mixed $decimalFixed = null;

    #[Decimal(2, 4)]
    public mixed $decimalRange = null;

    #[MultipleOf(0.25)]
    public mixed $multipleOf = null;
}
