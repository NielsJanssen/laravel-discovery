<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schedule\Traits;

use NielsJanssen\Laravel\Discovery\Schedule\Timezone;
use NielsJanssen\Laravel\Discovery\Schedule\WithoutOverlapping;

#[Timezone('Europe/London')]
#[WithoutOverlapping]
trait RunsInLondon {}
