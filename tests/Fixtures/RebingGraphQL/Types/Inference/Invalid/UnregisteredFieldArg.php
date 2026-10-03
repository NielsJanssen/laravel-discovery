<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class UnregisteredFieldArg
{
    #[Field]
    public function label(#[Arg(type: Unregistered::class)] mixed $thing): string
    {
        return 'never';
    }
}
