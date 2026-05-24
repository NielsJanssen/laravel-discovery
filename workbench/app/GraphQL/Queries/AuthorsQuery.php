<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Queries;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Query;

class AuthorsQuery extends Query
{
    protected $attributes = [
        'name' => 'authors',
    ];

    public function type(): Type
    {
        return Type::nonNull(Type::listOf(Type::nonNull(GraphQL::type('Author'))));
    }

    public function resolve($root, array $args): array
    {
        return [
            ['id' => 1, 'name' => 'F. Scott Fitzgerald'],
            ['id' => 2, 'name' => 'George Orwell'],
            ['id' => 3, 'name' => 'Harper Lee'],
        ];
    }
}
