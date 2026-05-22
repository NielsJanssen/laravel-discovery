<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use NielsJanssen\Laravel\Discovery\Feature\Event\EventHandler;

class MixedUnionEventListener
{
    #[EventHandler]
    public function handle(EventA|string $event): void {}
}
