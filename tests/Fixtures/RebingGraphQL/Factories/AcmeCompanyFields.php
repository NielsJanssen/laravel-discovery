<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;

final class AcmeCompanyFields implements TypeFactory
{
    public function fields(TypeContext $context): iterable
    {
        yield new Field(name: 'region', type: 'string', description: 'Sales region');
        yield new Field(name: 'tags', of: 'string', nullable: true);
        yield new Field(name: 'kind', type: 'string', deprecationReason: 'Use region');
    }
}
