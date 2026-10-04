<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use Illuminate\Validation\Rules\Password;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Tests\Fixtures\RebingGraphQL\MatchesConfirmation;

#[Input]
final readonly class Registration
{
    public function __construct(
        #[Field(rules: [new Password(8), new MatchesConfirmation()])]
        public string $password,
        public string $confirmation,
    ) {}
}
