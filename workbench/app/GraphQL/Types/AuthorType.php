<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Type as GraphQLType;

class AuthorType extends GraphQLType
{
    protected $attributes = [
        'name' => 'Author',
        'description' => 'An author',
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'The id of the author',
            ],
            'name' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'The name of the author',
            ],
        ];
    }
}
