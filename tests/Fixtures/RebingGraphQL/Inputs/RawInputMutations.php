<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

final class RawInputMutations
{
    /**
     * @param  array<string, mixed>  $raw
     */
    #[Mutation]
    public function createRaw(#[Arg(type: 'CreateBookInput')] array $raw): string
    {
        return (string) $raw['title'];
    }

    /**
     * @param  list<array<string, mixed>>  $reviews
     */
    #[Mutation]
    public function reviewMany(#[Arg] array $reviews): string
    {
        return count($reviews) . ' reviewed';
    }
}
