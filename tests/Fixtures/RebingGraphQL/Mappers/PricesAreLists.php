<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

final class PricesAreLists implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return $member->kind === MemberKind::MethodReturn && $member->name === 'prices'
            ? TypeRef::named('Money', list: true, nullableItems: true)
            : null;
    }
}
