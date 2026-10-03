<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Authorization\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class AuthorizeOnPlainMethod
{
    public string $name = 'x';

    #[Authorize]
    public function secret(): string
    {
        return 'secret';
    }
}
