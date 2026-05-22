<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Tempest\Reflection\Reflector;

/**
 * @template TReflector of Reflector
 */
class DiscoveredCommand
{
    public function __construct(
        /** @var TReflector */
        public Reflector $reflector,
        public ?ConsoleCommand $definition = null,
    ) {}
}
