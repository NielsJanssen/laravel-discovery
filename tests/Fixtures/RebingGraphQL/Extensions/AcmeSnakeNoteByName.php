<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;

#[TypeExtension('SnakeNote')]
final class AcmeSnakeNoteByName
{
    #[Field]
    public function wordLimit(): int
    {
        return 500;
    }
}
