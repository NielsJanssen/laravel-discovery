<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Validation;

/**
 * How the compiler descends into a member marked with #[Valid].
 */
enum Nesting
{
    /** Not marked, or nothing to descend into. */
    case None;

    /** Validate the object the member holds. */
    case Value;

    /** Validate every object the member iterates over, keyed as `member.0`, `member.1`, ... */
    case Each;
}
