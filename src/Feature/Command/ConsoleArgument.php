<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class ConsoleArgument
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
