<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Context;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;

class ContainerInjectionQuery
{
    #[Query(name: 'containerInjected')]
    public function resolve(
        string $name,
        #[Root]
        mixed $root,
        #[Context]
        mixed $context,
        ?ResolveInfo $info,
        ContainerService $service,
    ): string {
        return $name . $service->suffix();
    }
}
