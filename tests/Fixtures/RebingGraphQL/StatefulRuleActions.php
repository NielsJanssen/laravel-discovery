<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Illuminate\Validation\Rules\Password;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use Tests\Fixtures\RebingGraphQL\Inputs\Registration;

final class StatefulRuleActions
{
    #[Mutation]
    public function changePassword(
        #[Arg(rules: [new Password(8), new MatchesConfirmation()])]
        string $password,
        string $confirmation,
    ): string {
        return 'changed';
    }

    #[Mutation]
    public function register(Registration $registration): string
    {
        return 'registered';
    }
}
