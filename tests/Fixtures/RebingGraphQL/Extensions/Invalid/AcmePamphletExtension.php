<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;

#[TypeExtension('Pamphlet')]
final class AcmePamphletExtension
{
    #[Field]
    public function pages(): int
    {
        return 0;
    }
}
