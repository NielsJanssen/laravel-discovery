<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeNodeQueries
{
    /**
     * @return array{id: string}
     */
    #[Query(type: 'AcmeNode')]
    public function node(): array
    {
        return ['id' => 'W1'];
    }

    /**
     * @return array{id: string}
     */
    #[Query(type: 'AcmeWidget', schema: 'admin')]
    public function widget(): array
    {
        return ['id' => 'W1'];
    }
}
