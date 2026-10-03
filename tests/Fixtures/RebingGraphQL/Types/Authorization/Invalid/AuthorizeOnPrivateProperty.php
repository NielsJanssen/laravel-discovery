<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class AuthorizeOnPrivateProperty
{
    #[Authorize]
    private string $name = 'x';
}
