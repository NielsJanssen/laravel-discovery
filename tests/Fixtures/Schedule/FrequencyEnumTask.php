<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule;

use NielsJanssen\Laravel\Discovery\Schedule\Frequency;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;

class FrequencyEnumTask
{
    #[Scheduled(Frequency::Daily)]
    public function run(): void {}
}
