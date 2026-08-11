<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\Hydrator;

final class PlainCursorHydrator implements Hydrator
{
    public function hydrates(string $class): bool
    {
        return $class === PlainCursor::class;
    }

    public function hydrate(string $class, array $args): object
    {
        return new PlainCursor((string) ($args['cursor'] ?? ''));
    }
}
