<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

/** Records every member it is asked about and claims none. */
final class MemberRecordingMapper implements TypeMapper
{
    /** @var list<Member> */
    public static array $asked = [];

    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        self::$asked[] = $member;

        return null;
    }
}
