<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule\Traits;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Timezone;

#[Timezone('UTC')]
class TraitOverriddenTask
{
    use RunsInAmsterdam;

    #[Scheduled(Every::Hour)]
    public function sync(): void {}

    #[Scheduled(Every::Day)]
    #[Timezone('Asia/Tokyo')]
    public function report(): void {}
}
