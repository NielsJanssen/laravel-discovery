<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Tests\Fixtures\RebingGraphQL\Inputs\Chapter;

final class InputReturnQuery
{
    #[Query]
    public function chapter(): Chapter
    {
        return new Chapter('One');
    }
}
