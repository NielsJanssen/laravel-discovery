<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use NielsJanssen\Laravel\Discovery\Feature\Event\EventHandler;

class NoParameterListener
{
    #[EventHandler]
    public function handle(): void {}
}
