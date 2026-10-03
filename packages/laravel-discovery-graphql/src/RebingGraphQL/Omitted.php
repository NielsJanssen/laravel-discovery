<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** The value of an #[Input] property whose field the caller left out, as in `public string|Omitted $title = Omitted::Value`. */
enum Omitted
{
    case Value;
}
