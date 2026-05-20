<?php

namespace NielsJanssen\Laravel\Discovery\Feature\Command;

use Tempest\Reflection\Reflector;

/**
 * @template TReflector of Reflector
 */
class CommandDefinition
{
    public function __construct(
        /** @var TReflector */
        public Reflector $reflector,
        public ?Command $definition = null,
    ) {}
}
