<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use NielsJanssen\Laravel\Discovery\Event\EventHandler;

class NoParameterListener
{
    #[EventHandler]
    public function handle(): void {}
}
