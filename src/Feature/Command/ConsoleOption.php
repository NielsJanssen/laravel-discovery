<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class ConsoleOption
{
    public function __construct(
        public ?string $name = null,
        public ?string $shortcut = null,
        public ?string $description = null,
    ) {}
}
