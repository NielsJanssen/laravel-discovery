<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;

#[TypeExtension('Orphan')]
final class AcmeOrphanExtension
{
    #[Field]
    public function home(): string
    {
        return '';
    }
}
