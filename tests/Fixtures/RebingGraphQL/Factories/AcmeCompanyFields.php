<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;

final readonly class AcmeCompanyFields implements TypeFactory
{
    /**
     * @param  list<Field>|null  $fields  the fields to yield instead of the default ones
     */
    public function __construct(private ?array $fields = null) {}

    public function fields(TypeContext $context): iterable
    {
        return $this->fields ?? [
            new Field(name: 'region', type: 'string', description: 'Sales region'),
            new Field(name: 'tags', of: 'string', nullable: true),
            new Field(name: 'kind', type: 'string', deprecationReason: 'Use region'),
        ];
    }
}
