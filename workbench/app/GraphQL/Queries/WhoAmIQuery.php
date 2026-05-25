<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Context;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

class WhoAmIQuery
{
    #[Query(name: 'whoami')]
    public function resolve(
        #[Context]
        mixed $context,
        ResolveInfo $info,
    ): string {
        return sprintf(
            'field=%s context=%s',
            $info->fieldName,
            $context instanceof User ? 'user' : 'guest',
        );
    }
}
