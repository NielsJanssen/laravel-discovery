<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Paginated;

#[Paginated(defaultLimit: 30)]
trait PaginatesBooks {}
