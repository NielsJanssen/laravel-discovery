<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\ListOf;

final class ListOfOnObject
{
    #[ListOf(Country::class)]
    public Country $country;
}
