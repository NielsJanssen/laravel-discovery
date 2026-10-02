<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use GraphQL\Type\Definition\Type as GraphQLType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Rebing\GraphQL\Support\Type as RebingType;

#[Type]
final class TypeOnRebingType extends RebingType
{
    protected $attributes = [
        'name' => 'Both',
    ];

    public function fields(): array
    {
        return ['label' => ['type' => GraphQLType::string()]];
    }
}
