<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

final class NullableNotes implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return $member->name === 'notes' ? TypeRef::scalar('String', nullable: true) : null;
    }
}
