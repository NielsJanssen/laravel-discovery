<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use Carbon\CarbonImmutable;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final readonly class Event
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt = null,
    ) {}
}
