<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ProfileMutation
{
    public static ?Profile $received = null;

    #[Mutation]
    public function saveProfile(Profile $profile): string
    {
        self::$received = $profile;

        return $profile->name;
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
