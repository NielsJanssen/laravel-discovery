<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\MemberKind;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

/** Types an `array $drafts` parameter as `[CreateBookInput!]!`. */
final class DraftsMapper implements TypeMapper
{
    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        return $member->kind === MemberKind::Parameter && $member->name === 'drafts'
            ? TypeRef::named('CreateBookInput', list: true)
            : null;
    }
}
