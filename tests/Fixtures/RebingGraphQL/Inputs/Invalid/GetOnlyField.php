<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class GetOnlyField
{
    #[Field]
    public string $title {
        get => 'fixed';
    }
}
