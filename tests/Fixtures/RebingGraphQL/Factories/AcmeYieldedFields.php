<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;

final readonly class AcmeYieldedFields implements TypeFactory
{
    /**
     * @param  list<Field>  $fields
     */
    public function __construct(private array $fields = []) {}

    public function fields(TypeContext $context): iterable
    {
        return $this->fields;
    }
}
