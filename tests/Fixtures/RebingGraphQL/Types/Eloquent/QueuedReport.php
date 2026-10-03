<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use Illuminate\Bus\Queueable;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class QueuedReport
{
    use Queueable;

    public function __construct(
        public string $name = 'Monthly sales',
    ) {}
}
