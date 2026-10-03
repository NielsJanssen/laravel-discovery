<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;
use NielsJanssen\Laravel\Validation\Rule\Min;

#[Input]
final class RenameBook
{
    public string|Omitted $plain = Omitted::Value;

    public function __construct(
        #[Field(name: 'sub_title')]
        #[Min(3, message: 'A subtitle needs three letters.')]
        public string|Omitted $subtitle = Omitted::Value,
    ) {}
}
