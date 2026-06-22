<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation;

use NielsJanssen\Laravel\Validation\Rule\ListOf;
use NielsJanssen\Laravel\Validation\Rule\Valid;

final class BothNestingAttributes
{
    #[Valid]
    #[ListOf(Country::class)]
    public array $things = [];
}
