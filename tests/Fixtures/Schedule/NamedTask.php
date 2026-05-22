<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class NamedTask
{
    #[Scheduled('hour', name: 'my-named-task')]
    public function run(): void {}
}
