<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeNoteQuery
{
    #[Query]
    public function board(): AcmeLooseBoard
    {
        return new AcmeLooseBoard();
    }
}
