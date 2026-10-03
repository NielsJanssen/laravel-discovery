<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Loaders\Novel;

#[Type]
final class RelationOnPlainClass
{
    #[Field(of: Novel::class), Relation]
    public function novels(): array
    {
        return [];
    }
}
