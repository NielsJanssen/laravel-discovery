<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Customer
{
    public function __construct(
        public string $tier,
    ) {}
}
