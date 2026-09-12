<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

/**
 * $title is renamed to `name`, which is already another parameter's PHP name — mapping the args
 * back onto parameters would silently drop one of them, so discovery must refuse this.
 */
class CollidingArgNameQuery
{
    #[Query(name: 'collidingArgHello')]
    public function resolve(
        #[Arg('name')]
        string $title,
        string $name,
    ): string {
        return "{$title} {$name}";
    }
}
