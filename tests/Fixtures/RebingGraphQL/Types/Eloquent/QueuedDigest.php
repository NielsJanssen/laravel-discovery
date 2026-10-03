<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Types\Eloquent\Concerns\QueuesDigests;

#[Type]
final class QueuedDigest
{
    use QueuesDigests;

    public function __construct(
        public string $name = 'Weekly digest',
    ) {}
}
