<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use NielsJanssen\Laravel\Discovery\Event\EventHandler;

class ExplicitEventListener
{
    #[EventHandler(event: EventB::class)]
    public function handle(EventA $event): void {}
}
