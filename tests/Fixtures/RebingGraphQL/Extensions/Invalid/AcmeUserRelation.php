<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;

#[TypeExtension('AcmeUser')]
final class AcmeUserRelation
{
    /**
     * @return list<string>
     */
    #[Field(of: 'String'), Relation('posts')]
    public function posts(): array
    {
        return [];
    }
}
