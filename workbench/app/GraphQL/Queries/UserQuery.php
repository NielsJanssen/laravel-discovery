<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class UserQuery
{
    #[Query(type: 'User', name: 'user')]
    public function resolve(#[Arg('id')] User $user): User
    {
        return $user;
    }
}
