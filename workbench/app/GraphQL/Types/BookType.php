<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Types;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Type as GraphQLType;

class BookType extends GraphQLType
{
    protected $attributes = [
        'name' => 'Book',
        'description' => 'A book',
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'The id of the book',
            ],
            'title' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'The title of the book',
            ],
            'author' => [
                'type' => Type::string(),
                'description' => 'The name of the author',
            ],
        ];
    }
}
