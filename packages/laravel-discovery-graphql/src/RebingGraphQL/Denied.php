<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** What a field reports when #[Authorize] denies it. */
enum Denied
{
    case Null;
    case Error;
}
