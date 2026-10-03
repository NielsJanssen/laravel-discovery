<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Inference;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ExplicitArgTypesQuery
{
    /**
     * @param  list<string>  $tags
     * @param  list<string>|null  $maybeTags
     */
    #[Query]
    public function explicit(
        #[Arg(type: 'ID')]
        string $id,
        #[Arg(type: 'ID')]
        ?string $maybeId,
        #[Arg(type: 'String')]
        string $label,
        #[Arg(type: 'String')]
        ?string $maybeLabel,
        #[Arg(type: '[String!]')]
        array $tags,
        #[Arg(type: '[String!]')]
        ?array $maybeTags,
    ): string {
        return implode(',', [$id, $maybeId ?? '-', $label, $maybeLabel ?? '-', implode('+', $tags), implode('+', $maybeTags ?? [])]);
    }
}
