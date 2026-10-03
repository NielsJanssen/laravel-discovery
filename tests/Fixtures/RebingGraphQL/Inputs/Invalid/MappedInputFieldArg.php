<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class MappedInputFieldArg
{
    /**
     * @param  list<array<string, mixed>>  $drafts
     */
    #[Field]
    public function count(#[Arg] array $drafts): int
    {
        return count($drafts);
    }
}
