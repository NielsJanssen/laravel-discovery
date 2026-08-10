<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Max;
use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\NumericType;
use NielsJanssen\Laravel\Validation\Rule\Rule;

/** Wrapping a named attribute in #[Rule] is how it gets a custom message. */
final class WrappedMessageForm
{
    #[Rule(new Min(5), message: 'Give it at least five.')]
    public string $name = 'Niels';

    #[Rule([new Min(2), new Max(4)], message: 'Between two and four, please.')]
    public string $code = 'abc';

    /** A wrapped type rule keys under the stripped name, not "integer_type". */
    #[Rule(new NumericType(), message: 'Numbers only.')]
    public string $amount = '10';

    /** Still works with no message at all. */
    #[Rule(new Min(3))]
    public string $plain = 'abcd';
}
