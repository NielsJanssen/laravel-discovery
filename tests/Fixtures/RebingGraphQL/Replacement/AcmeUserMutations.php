<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeUserMutations
{
    /** @var list<string> */
    public static array $received = [];

    #[Mutation]
    public function createUser(AcmeCreateUser $input): string
    {
        self::$received = [$input::class];

        return $input->name;
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
