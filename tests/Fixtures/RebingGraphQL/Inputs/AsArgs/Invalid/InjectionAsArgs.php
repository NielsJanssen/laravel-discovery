<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs\Invalid;

use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\AsArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class InjectionAsArgs
{
    #[Query]
    public function search(#[AsArgs] ResolveInfo $info): string
    {
        return $info->fieldName;
    }
}
