<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use Carbon\CarbonImmutable;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;

#[Input]
final readonly class Reschedule
{
    public function __construct(
        public CarbonImmutable|Omitted $startsAt = Omitted::Value,
    ) {}
}
