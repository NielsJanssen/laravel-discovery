<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;

final class AcmeListedProvider implements TypeProvider
{
    public function types(): iterable
    {
        yield new TypeDefinition(
            name: 'AcmeOtherListed',
            kind: Position::Output,
            fields: static fn() => [new Field(name: 'reference', type: 'string')],
            class: AcmeListed::class,
        );
    }
}
