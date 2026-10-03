<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class AuthorizeOnScalar
{
    #[Authorize('view')]
    public string $title = '';
}
