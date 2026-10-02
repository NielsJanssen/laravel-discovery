<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** Where a type field's value comes from at resolve time. */
enum FieldSource: string
{
    case Factory = 'factory';
    case Method = 'method';
    case Property = 'property';
}
