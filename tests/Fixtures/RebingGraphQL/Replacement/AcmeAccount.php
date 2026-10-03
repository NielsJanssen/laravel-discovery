<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
#[Input]
class AcmeAccount
{
    public function __construct(
        public string $label = 'Main',
    ) {}
}
