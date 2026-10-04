<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\Enums\Orphan;

#[TypeExtension(AcmeReport::class)]
final class AcmeReportExtras
{
    #[Field]
    public function orphan(): Orphan
    {
        return Orphan::Alone;
    }
}
