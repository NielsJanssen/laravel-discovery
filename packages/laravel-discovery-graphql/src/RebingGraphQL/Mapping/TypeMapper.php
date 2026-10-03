<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

/** Claims the GraphQL type of a field, arg or action return before the built-in inference; tag it with TAG. */
interface TypeMapper
{
    public const string TAG = 'graphql.type_mappers';

    /** Asked at discovery time and cached with the items, so it decides from reflection alone; null means not mine. */
    public function map(TypeReflector $type, Member $member): ?TypeRef;
}
