<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Reference;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class AuthorSummary
{
    public function __construct(
        public string $name,
    ) {}
}
