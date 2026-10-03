<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use Carbon\CarbonImmutable;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Listing
{
    public function __construct(
        public CarbonImmutable $listedAt,
        public ?CarbonImmutable $delistedAt = null,
    ) {}
}
