<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule\Traits;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class NestedTraitTask
{
    use WrapsRunsInAmsterdam;

    #[Scheduled(Every::Hour)]
    public function sync(): void {}
}
