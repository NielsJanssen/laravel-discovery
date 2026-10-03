<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Linked
{
    public ?self $next = null;

    public int|string $code = 0;

    #[Field]
    public function itself(): static
    {
        return $this;
    }
}
