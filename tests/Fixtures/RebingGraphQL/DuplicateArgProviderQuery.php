<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use GraphQL\Type\Definition\Type as GraphQLType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionArgProvider;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class ClashingArgsProviderA implements ActionArgProvider
{
    public function provideArgs(): array
    {
        return ['shared' => ['type' => GraphQLType::string()]];
    }

    public function provideValueObjects(): array
    {
        return [];
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
final class ClashingArgsProviderB implements ActionArgProvider
{
    public function provideArgs(): array
    {
        return ['shared' => ['type' => GraphQLType::int()]];
    }

    public function provideValueObjects(): array
    {
        return [];
    }
}

class DuplicateArgProviderQuery
{
    #[Query(name: 'duplicateArgs')]
    #[ClashingArgsProviderA]
    #[ClashingArgsProviderB]
    public function resolve(): string
    {
        return 'never reached';
    }
}
