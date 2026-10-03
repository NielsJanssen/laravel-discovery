<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use NielsJanssen\Laravel\Validation\Rule\Size;

#[Type, Input]
final class Address
{
    public function __construct(
        public string $street,
        #[Field(rules: ['min:3'])]
        public string $city,
        #[Size(2)]
        public string $country,
    ) {}
}
