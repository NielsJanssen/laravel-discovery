<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Fields;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class FieldSourcesQuery
{
    #[Query(type: FieldSourcesType::class)]
    public function sources(): FieldSourcesType
    {
        return new FieldSourcesType();
    }
}
