<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;

#[TypeExtension('AcmeUser')]
final class AcmeDuplicateFactory implements TypeFactory
{
    public function fields(TypeContext $context): iterable
    {
        yield new Field(name: 'name', type: 'string');
    }
}
