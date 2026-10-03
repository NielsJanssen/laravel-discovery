<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Relation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\Loaders\Writer;

#[TypeExtension('AcmeStockedNovel')]
final class AcmeStockedNovelAuthor
{
    #[Field(type: Writer::class), Relation('writer')]
    public function writtenBy(): ?Writer
    {
        return null;
    }
}
