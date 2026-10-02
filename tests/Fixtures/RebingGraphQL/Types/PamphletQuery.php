<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class PamphletQuery
{
    /**
     * @return array{title: string}
     */
    #[Query(type: 'Pamphlet', schema: 'pamphlets')]
    public function pamphlet(): array
    {
        return ['title' => 'Common Sense'];
    }
}
