<?php

declare(strict_types=1);

namespace Tests\Fixtures\Event;

use NielsJanssen\Laravel\Discovery\Feature\Event\EventHandler;

class ScalarParameterListener
{
    #[EventHandler]
    public function handle(string $event): void {}
}
