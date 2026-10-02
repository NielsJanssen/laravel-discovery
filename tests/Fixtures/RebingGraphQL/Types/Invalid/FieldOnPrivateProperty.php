<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class FieldOnPrivateProperty
{
    #[Field]
    private string $secret = 'secret';

    public function secret(): string
    {
        return $this->secret;
    }
}
