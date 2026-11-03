<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule\Traits;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class TraitDecoratedTask
{
    use RunsInAmsterdam;
    use RunsInLondon;

    #[Scheduled(Every::Hour)]
    public function sync(): void {}
}
