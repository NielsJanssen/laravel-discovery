<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Decorators;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class HeadlineQuery
{
    #[Query]
    public function headline(): Headline
    {
        return new Headline();
    }
}
