<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\OnOneServer;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

#[OnOneServer]
class OnOneServerTask
{
    #[Scheduled(Every::Hour)]
    public function run(): void {}
}
