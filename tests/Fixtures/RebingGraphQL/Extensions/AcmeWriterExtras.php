<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\Loaders\Novel;
use Tests\Fixtures\RebingGraphQL\Loaders\Writer;

#[TypeExtension(Writer::class)]
final class AcmeWriterExtras
{
    /**
     * @return list<Novel>
     */
    #[Field(of: Novel::class), Relation('novels')]
    public function books(): array
    {
        return [];
    }
}
