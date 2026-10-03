<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

final class MoneyMapper implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return $type->matches(Money::class) ? TypeRef::named('Money') : null;
    }
}
