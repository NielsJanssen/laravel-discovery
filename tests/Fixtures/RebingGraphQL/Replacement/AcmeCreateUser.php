<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
class AcmeCreateUser
{
    public function __construct(
        #[Field(rules: ['min:2'])]
        public string $name,
    ) {}
}
