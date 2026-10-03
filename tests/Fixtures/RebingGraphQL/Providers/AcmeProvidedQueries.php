<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeProvidedQueries
{
    /**
     * @return list<array<string, mixed>>
     */
    #[Query(of: 'AcmeDepot')]
    public function depots(): array
    {
        return [['code' => 'D1'], ['code' => 'D2']];
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    #[Query]
    public function describeFilter(#[Arg(type: 'AcmeFilter')] array $filter): string
    {
        return json_encode($filter, JSON_THROW_ON_ERROR);
    }
}
