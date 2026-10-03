<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class AcmeMutations
{
    /** @var list<string> */
    public static array $received = [];

    #[Mutation]
    public function createUser(AcmeCreateUser $input): string
    {
        self::$received = [$input::class];

        return $input->name;
    }

    #[Mutation]
    public function createTeam(AcmeCreateTeam $team): string
    {
        self::$received = [$team->owner::class, ...array_map(static fn(AcmeCreateUser $member): string => $member::class, $team->members)];

        return $team->owner->name;
    }

    #[Query]
    public function account(): AcmeAccount
    {
        return new AcmeAccount();
    }

    #[Mutation]
    public function saveAccount(AcmeAccount $account): string
    {
        return $account->label;
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
