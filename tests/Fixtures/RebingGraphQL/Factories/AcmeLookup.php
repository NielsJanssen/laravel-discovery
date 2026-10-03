<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(naming: FieldCase::Snake, factory: AcmeLookupFields::class)]
final class AcmeLookup
{
    public string $name = 'Acme';
}
