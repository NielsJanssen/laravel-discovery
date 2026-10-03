<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

/** Claims every member it is asked about. */
final class GreedyMapper implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return TypeRef::scalar('String');
    }
}
