<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\BookSearch;

#[Type]
final class FieldMethodAsArgs
{
    #[Field]
    public function matches(#[AsArgs] BookSearch $search): int
    {
        return 0;
    }
}
