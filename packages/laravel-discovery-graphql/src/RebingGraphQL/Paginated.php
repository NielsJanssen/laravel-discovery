<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use GraphQL\Type\Definition\Type as GraphQLType;
use Rebing\GraphQL\Support\Facades\GraphQL;
use RuntimeException;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
readonly class Paginated implements ActionTypeBuilder, ActionArgProvider
{
    public function __construct(
        public int $defaultLimit = 20,
    ) {}

    public function buildType(Action $action): GraphQLType
    {
        if ($action->type === null || in_array($action->type, ['string', 'int', 'float', 'bool', 'void'], true)) {
            throw new RuntimeException('#[Paginated] requires an explicit object type on the #[Query]/#[Mutation] attribute.');
        }

        return GraphQL::paginate($action->type);
    }

    public function provideArgs(): array
    {
        return [
            'page' => [
                'type'         => GraphQLType::int(),
                'defaultValue' => 1,
                'rules'        => ['integer', 'min:1'],
            ],
            'limit' => [
                'type'         => GraphQLType::int(),
                'defaultValue' => $this->defaultLimit,
                'rules'        => ['integer', 'min:1'],
            ],
        ];
    }

    public function provideValueObjects(): array
    {
        return [Pagination::class];
    }
}
