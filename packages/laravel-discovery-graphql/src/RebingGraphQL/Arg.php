<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Arg
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public array|\Closure|null $rules = null,
        public readonly ?string $description = null,
        public readonly ?string $deprecationReason = null,
    ) {}
}
