<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Query implements Action
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $schema = null,
        public ?string $description = null,
        public bool $list = false,
        public bool $nullable = false,
    ) {}
}
