<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeDefaultPamphletQuery
{
    /**
     * @return array{title: string}
     */
    #[Query(type: 'Pamphlet')]
    public function defaultPamphlet(): array
    {
        return ['title' => 'Default'];
    }
}
