<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class NonNullNotesQuery
{
    #[Query]
    public function annotate(string $notes): string
    {
        return $notes;
    }
}
