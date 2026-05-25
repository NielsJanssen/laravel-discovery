<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Command;

use Tempest\Reflection\Reflector;

/**
 * @template TReflector of Reflector
 */
class DiscoveredCommand
{
    public function __construct(
        /** @var Reflector */
        public Reflector $reflector,
        public ?ConsoleCommand $definition = null,
    ) {}
}
