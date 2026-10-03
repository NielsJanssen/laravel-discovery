<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorization;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Context;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use Tests\Fixtures\RebingGraphQL\ContainerService;
use Workbench\App\Models\User;

final class NotPreemptedQuery
{
    #[Query]
    #[Authorize]
    public function owner(
        #[Arg('id')]
        User $user,
        ContainerService $service,
        Authorization $auth,
        #[Root]
        mixed $root,
        #[Context]
        mixed $context,
        ResolveInfo $info,
        int $note,
    ): string {
        return $user->name . $service->suffix();
    }
}
