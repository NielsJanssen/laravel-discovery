<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ConsoleCommand
{
    public function __construct(
        public string $name,
        public ?string $description = null,

        /** list<string> */
        public array $aliases = [],
    ) {}
}
