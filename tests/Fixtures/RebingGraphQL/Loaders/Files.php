<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use Attribute;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\BatchedFieldDecorator;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\ResolvesThroughBatchLoader;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Files implements BatchedFieldDecorator
{
    use ResolvesThroughBatchLoader;

    public function __construct(public string $collection = 'default') {}

    public function loader(): string
    {
        return FileLoader::class;
    }

    public function options(string $fieldName): array
    {
        return ['collection' => $this->collection];
    }
}
