<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Attribute;
use GraphQL\Type\Definition\Type as GraphQLType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionArgProvider;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class TestPaged implements ActionArgProvider
{
    public function provideArgs(): array
    {
        return ['offset' => ['type' => GraphQLType::int(), 'defaultValue' => 1]];
    }

    public function provideValueObjects(): array
    {
        return [TestPage::class];
    }
}
