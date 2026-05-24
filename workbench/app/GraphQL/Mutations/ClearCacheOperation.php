<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Mutations;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;

class ClearCacheOperation
{
    #[Mutation(name: 'clearCache')]
    public function resolve(): void {}
}
