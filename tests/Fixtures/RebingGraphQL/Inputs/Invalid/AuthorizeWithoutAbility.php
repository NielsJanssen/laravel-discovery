<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Workbench\App\Models\User;

#[Input]
final class AuthorizeWithoutAbility
{
    #[Authorize]
    public User $owner;
}
