<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;

final class AcmeShipmentProvider implements TypeProvider
{
    public function types(): iterable
    {
        yield new TypeDefinition(
            name: 'AcmeConsignment',
            kind: Position::Output,
            fields: static fn() => [
                new Field(name: 'reference', type: 'string'),
                new Field(name: 'weight', type: 'int'),
            ],
            class: AcmeShipment::class,
        );
    }
}
