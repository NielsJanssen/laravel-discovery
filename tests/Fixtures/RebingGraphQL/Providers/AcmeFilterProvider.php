<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;

final class AcmeFilterProvider implements TypeProvider
{
    public function types(): iterable
    {
        yield new TypeDefinition(
            name: 'AcmeFilter',
            kind: Position::Input,
            fields: static fn() => [
                new Field(name: 'term', type: 'string'),
                new Field(name: 'limit', type: 'int', nullable: true, description: 'At most this many'),
                new Field(name: 'tags', of: 'string', nullable: true),
            ],
            description: 'What to look for',
        );
    }
}
