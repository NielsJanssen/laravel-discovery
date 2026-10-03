<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeRef;
use Tempest\Reflection\TypeReflector;

/** The registered TypeMapper implementations; the first non-null result wins. */
final readonly class TypeMapperRegistry
{
    public function __construct(
        /** @var iterable<TypeMapper> */
        private iterable $mappers = [],
    ) {}

    public function map(TypeReflector $type, Member $member): ?TypeRef
    {
        foreach ($this->mappers as $mapper) {
            $ref = $mapper->map($type, $member);

            if ($ref !== null) {
                return $ref;
            }
        }

        return null;
    }
}
