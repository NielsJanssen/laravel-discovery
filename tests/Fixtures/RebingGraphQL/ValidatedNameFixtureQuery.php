<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

class ValidatedNameFixtureQuery
{
    #[Query(name: 'fixtureValidatedHello')]
    public function resolve(
        #[Arg(rules: ['min:3'])]
        string $name,
    ): string {
        return "hi, {$name}";
    }
}
