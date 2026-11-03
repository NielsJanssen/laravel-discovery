<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;

#[Authorize(message: 'Login required')]
trait RequiresLogin {}
