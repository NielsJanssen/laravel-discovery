<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\Each;
use NielsJanssen\Laravel\Validation\Rule\ListOf;
use NielsJanssen\Laravel\Validation\Rule\Min;
use NielsJanssen\Laravel\Validation\Rule\Rule;

/** #[Each] element rules carrying their own messages. */
final class MessagedElements
{
    #[Each(new Rule('email', message: 'That is not an email.'))]
    public array $recipients = [];

    /** A wrapped named attribute inside #[Each]. */
    #[Each(new Rule(new Min(3), message: 'Too short an alias.'))]
    public array $aliases = [];

    /** Composed with #[ListOf]: the element message applies to the element itself. */
    #[ListOf(Country::class)]
    #[Each(new Rule('required', message: 'A country is required here.'))]
    public array $countries = [];
}
