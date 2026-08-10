<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/**
 * The built-in hydrator: value objects that implement ComposedFromArgs build themselves. This is
 * what backs #[Paginated]'s Pagination and #[Sortable]'s Sort, and it holds no privileged position —
 * discovery asks the hydrator registry, not this class.
 */
final class ComposedFromArgsHydrator implements ArgumentHydrator
{
    public function hydrates(string $class): bool
    {
        return is_a($class, ComposedFromArgs::class, allow_string: true);
    }

    public function hydrate(string $class, array $args): object
    {
        assert(is_a($class, ComposedFromArgs::class, allow_string: true));

        return $class::fromArgs($args);
    }
}
