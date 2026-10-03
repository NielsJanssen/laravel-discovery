<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Novel
{
    public function __construct(
        public string $title = 'Dune',
        public ?Novel $sequel = null,
    ) {}
}
