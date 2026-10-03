<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use Attribute;

/** Resolves the field by eager loading an Eloquent relation for every parent at once. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final readonly class Relation implements BatchedFieldDecorator
{
    use ResolvesThroughBatchLoader;

    public function __construct(
        /** The relation method; defaults to the member's PHP name. */
        public ?string $relation = null,
    ) {}

    public function loader(): string
    {
        return RelationLoader::class;
    }

    public function options(string $fieldName): array
    {
        return ['relation' => $this->relation ?? $fieldName];
    }
}
