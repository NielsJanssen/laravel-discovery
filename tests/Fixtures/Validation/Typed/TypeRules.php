<?php

declare(strict_types=1);

namespace Tests\Fixtures\Validation\Typed;

use NielsJanssen\Laravel\Validation\Rule\ArrayType;
use NielsJanssen\Laravel\Validation\Rule\Boolean;
use NielsJanssen\Laravel\Validation\Rule\Integer;
use NielsJanssen\Laravel\Validation\Rule\ListType;
use NielsJanssen\Laravel\Validation\Rule\Numeric;
use NielsJanssen\Laravel\Validation\Rule\StringType;

final class TypeRules
{
    #[StringType]
    public mixed $text = 'abc';

    #[Integer]
    public mixed $count = 1;

    #[Integer(strict: true)]
    public mixed $strictCount = 1;

    #[Numeric]
    public mixed $amount = 1.5;

    #[Boolean]
    public mixed $flag = true;

    #[Boolean(strict: true)]
    public mixed $strictFlag = true;

    #[ArrayType]
    public mixed $bag = [];

    #[ArrayType(keys: ['name', 'email'])]
    public mixed $keyed = [];

    #[ListType]
    public mixed $items = [];
}
