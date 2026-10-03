<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(description: 'An Acme user')]
class AcmeUser
{
    public function __construct(
        public string $name = 'Ada',
    ) {}
}
