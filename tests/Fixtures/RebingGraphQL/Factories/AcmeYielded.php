<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(factory: AcmeYieldedFields::class)]
final class AcmeYielded
{
    public string $name = 'Acme';
}
