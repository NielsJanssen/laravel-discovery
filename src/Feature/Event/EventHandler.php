<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\Feature\Event;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class EventHandler
{
    public function __construct(
        public ?string $event = null,
    ) {}
}
