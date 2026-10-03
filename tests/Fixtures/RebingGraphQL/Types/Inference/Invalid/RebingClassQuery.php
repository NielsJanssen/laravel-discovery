<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\Types\PamphletType;

final class RebingClassQuery
{
    /** @return array{title: string} */
    #[Query(type: PamphletType::class)]
    public function pamphlet(): array
    {
        return ['title' => 'Common Sense'];
    }
}
