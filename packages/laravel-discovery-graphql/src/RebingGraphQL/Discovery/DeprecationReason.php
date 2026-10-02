<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use Deprecated;

/** Turns a native #[\Deprecated] into a GraphQL deprecation reason. */
final class DeprecationReason
{
    public static function from(?Deprecated $deprecated): ?string
    {
        if ($deprecated === null) {
            return null;
        }

        $message = $deprecated->message;
        $since = $deprecated->since;

        return match (true) {
            $message !== null && $since !== null => "$message (since $since)",
            $message !== null => $message,
            $since !== null => "Deprecated since $since",
            default => 'Deprecated',
        };
    }
}
