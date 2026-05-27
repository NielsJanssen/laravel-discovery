<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\CurrentUser;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class CurrentUserQuery
{
    #[Query(name: 'currentUser')]
    public function resolve(
        #[CurrentUser]
        ?User $user,
        #[Config('app.name')]
        string $appName,
    ): string {
        $email = $user?->email ?? 'guest';

        return "{$appName}:{$email}";
    }
}
