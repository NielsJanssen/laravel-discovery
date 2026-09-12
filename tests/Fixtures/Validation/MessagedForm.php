<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Numeric;
use NielsJanssen\Laravel\Validation\Rule\Required;
use NielsJanssen\Laravel\Validation\Rule\Rule;

/** Every attribute takes a message of its own; nothing has to be wrapped to carry one. */
final class MessagedForm
{
    #[Min(5, message: 'Give it at least five.')]
    public string $name = 'Niels';

    /** A type attribute keys under the rule Laravel sees, not under the attribute name. */
    #[Numeric(message: 'Numbers only.')]
    public string $amount = '10';

    /** An argument-less attribute inherits the message constructor. */
    #[Required(message: 'The title is not optional.')]
    public string $title = 'Sale';

    /** A foreign rule object is handed to Laravel untouched. */
    #[Rule(new EvenNumber())]
    public int $age = 4;

    #[Rule('min:18', message: 'Adults only.')]
    public int $years = 30;

    /** Still works with no message at all. */
    #[Min(3)]
    public string $plain = 'abcd';
}
