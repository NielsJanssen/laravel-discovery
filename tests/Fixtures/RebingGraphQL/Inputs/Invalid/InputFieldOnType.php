<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\Inputs\Chapter;

#[Type]
final class InputFieldOnType
{
    public function __construct(public Chapter $chapter) {}
}
