<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use NielsJanssen\Laravel\Discovery\Event\EventHandler;

class TrackingEventListener
{
    public static bool $invoked = false;

    #[EventHandler]
    public function handle(EventA $event): void
    {
        self::$invoked = true;
    }
}
