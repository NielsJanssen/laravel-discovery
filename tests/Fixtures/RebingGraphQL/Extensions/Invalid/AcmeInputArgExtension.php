<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;

#[TypeExtension('AcmeUser')]
final class AcmeInputArgExtension
{
    /**
     * @param  array<string, mixed>  $filter
     */
    #[Field]
    public function peers(#[Arg(type: 'UnusedInput')] array $filter): string
    {
        return '';
    }
}
