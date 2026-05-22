<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use NielsJanssen\Laravel\Discovery\Event\EventHandler;

class UnionEventListener
{
    #[EventHandler]
    public function handle(EventA|EventB $event): void {}
}
