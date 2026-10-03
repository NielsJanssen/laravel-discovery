<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\Member;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping\TypeMapper;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

/** Records the type names it is asked about and claims each as a String. */
final class RecordingMapper implements TypeMapper
{
    /** @var array<string, TypeReflector> */
    public static array $seen = [];

    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        self::$seen[$member->name] = $type;

        return TypeRef::scalar('String');
    }
}
