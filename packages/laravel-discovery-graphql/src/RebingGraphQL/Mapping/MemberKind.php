<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Mapping;

enum MemberKind: string
{
    case Property = 'property';
    case MethodReturn = 'method_return';
    case Parameter = 'parameter';
}
