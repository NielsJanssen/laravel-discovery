<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;

#[TypeExtension('AcmeUser')]
final class AcmeUserRename
{
    #[Field]
    public function name(): string
    {
        return '';
    }
}
