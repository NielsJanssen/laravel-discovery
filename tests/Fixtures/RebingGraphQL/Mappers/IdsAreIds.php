<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

final class IdsAreIds implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return $member->name === 'id' && in_array($type->getName(), ['int', 'string'], true)
            ? TypeRef::named('ID', nullable: $type->isNullable())
            : null;
    }
}
