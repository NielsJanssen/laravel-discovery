<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use Tests\Fixtures\RebingGraphQL\Replacement\AcmeCreateUser;

final class AcmeFlattenedMutation
{
    #[Mutation]
    public function createUser(#[AsArgs] AcmeCreateUser $input): string
    {
        return $input->name;
    }
}
