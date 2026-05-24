<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\OnOneServer;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Timezone;
use NielsJanssen\Laravel\Discovery\Schedule\WithoutOverlapping;

#[Timezone('Europe/Amsterdam')]
#[WithoutOverlapping(60)]
#[OnOneServer]
class ClassDecoratorTask
{
    #[Scheduled(Every::Hour)]
    public function sync(): void {}

    #[Scheduled(Every::Day)]
    public function report(): void {}
}
