<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ListingQuery
{
    #[Query]
    public function listing(): Listing
    {
        return new Listing(CarbonImmutable::parse('2026-01-02T03:04:05+00:00'));
    }

    #[Query]
    public function dayAfter(#[Arg] CarbonImmutable $date): CarbonInterface
    {
        return $date->addDay();
    }
}
