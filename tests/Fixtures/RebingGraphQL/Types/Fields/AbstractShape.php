<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Fields;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
abstract class AbstractShape
{
    public string $label = 'shape';

    #[Query]
    public function shapeName(): string
    {
        return 'never registered';
    }
}
