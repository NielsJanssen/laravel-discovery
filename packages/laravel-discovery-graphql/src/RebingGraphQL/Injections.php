<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;

/** Fills the resolver-call values (#[Root], #[Context], ResolveInfo) a method asks for. */
final class Injections
{
    /**
     * @param  array<string, 'root'|'context'|'info'>  $injections  keyed by paramName
     * @return array<string, mixed> keyed by paramName
     */
    public static function values(array $injections, mixed $root, mixed $context, ?ResolveInfo $info): array
    {
        $values = [];

        foreach ($injections as $paramName => $kind) {
            $values[$paramName] = match ($kind) {
                'root' => $root,
                'context' => $context,
                'info' => $info,
            };
        }

        return $values;
    }
}
