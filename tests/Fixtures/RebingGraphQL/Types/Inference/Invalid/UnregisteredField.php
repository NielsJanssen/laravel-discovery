<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class UnregisteredField
{
    public function __construct(
        public Unregistered $thing = new Unregistered(),
    ) {}
}
