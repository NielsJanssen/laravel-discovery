<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Inputs\Chapter;

#[Type]
final class FieldMethodInputArg
{
    #[Field]
    public function matches(Chapter $chapter): bool
    {
        return $chapter->title !== '';
    }
}
