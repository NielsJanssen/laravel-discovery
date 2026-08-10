<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use Attribute;
use GraphQL\Type\Definition\Type as GraphQLType;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\ActionArgProvider;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class CursorArgs implements ActionArgProvider
{
    public function provideArgs(): array
    {
        return ['cursor' => ['type' => GraphQLType::string(), 'defaultValue' => '']];
    }

    public function provideValueObjects(): array
    {
        return [PlainCursor::class];
    }
}
