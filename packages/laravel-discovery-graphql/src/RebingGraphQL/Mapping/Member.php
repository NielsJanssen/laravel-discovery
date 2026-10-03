<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;

/** The property, method return or parameter a TypeMapper is asked about. */
final readonly class Member
{
    /**
     * @param  string  $name  the property, method or parameter name
     * @param  class-string  $declaringClass
     */
    public function __construct(
        public string $name,
        public string $declaringClass,
        public Position $position,
        public MemberKind $kind,
    ) {}
}
