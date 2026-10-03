<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class RawInputFieldArg
{
    /**
     * @param  array<string, mixed>  $raw
     */
    #[Field]
    public function publisher(#[Arg(type: 'CreateBookInput')] array $raw): string
    {
        return (string) $raw['publisher'];
    }
}
