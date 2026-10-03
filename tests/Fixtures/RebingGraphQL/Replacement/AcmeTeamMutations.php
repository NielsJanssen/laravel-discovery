<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

final class AcmeTeamMutations
{
    #[Mutation]
    public function createTeam(AcmeCreateTeam $team): string
    {
        AcmeUserMutations::$received = [$team->owner::class, ...array_map(static fn(AcmeCreateUser $member): string => $member::class, $team->members)];

        return $team->owner->name;
    }
}
