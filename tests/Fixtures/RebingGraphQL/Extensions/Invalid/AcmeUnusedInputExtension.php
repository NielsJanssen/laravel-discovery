<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;

#[TypeExtension('UnusedInput')]
final class AcmeUnusedInputExtension
{
    #[Field]
    public function shade(): string
    {
        return '';
    }
}
