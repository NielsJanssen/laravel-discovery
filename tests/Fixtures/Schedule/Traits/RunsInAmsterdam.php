<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule\Traits;

use NielsJanssen\Laravel\Discovery\Schedule\Every;
use NielsJanssen\Laravel\Discovery\Schedule\OnOneServer;
use NielsJanssen\Laravel\Discovery\Schedule\Scheduled;
use NielsJanssen\Laravel\Discovery\Schedule\Timezone;

#[Timezone('Europe/Amsterdam')]
#[OnOneServer]
#[Scheduled(Every::Minute)]
trait RunsInAmsterdam {}
