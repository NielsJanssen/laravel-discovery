<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Schemas\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(schema: 'admin')]
final class AcmeAdminNote
{
    public string $text = 'note';
}
