<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Mutation;

/** A hand-written Rebing mutation taking a discovered input, so our own authorize() never runs for it. */
final class RebingCreateBook extends Mutation
{
    protected $attributes = [
        'name' => 'rebingCreateBook',
    ];

    public function type(): Type
    {
        return Type::nonNull(Type::string());
    }

    public function args(): array
    {
        return [
            'input' => ['type' => Type::nonNull(GraphQL::type('CreateBookInput'))],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function resolve(mixed $root, array $args): string
    {
        return $args['input']['title'];
    }
}
