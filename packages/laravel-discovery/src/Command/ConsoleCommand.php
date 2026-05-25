<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ConsoleCommand
{
    public function __construct(
        public string $name,
        public ?string $description = null,

        /** @var list<string> */
        public array $aliases = [],

        /** @var list<callable|class-string<CommandMiddleware>> */
        public array $middleware = [],
    ) {}
}
