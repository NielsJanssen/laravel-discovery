<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Mutations;

use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Mutation;

class RebingNativeMutation extends Mutation
{
    protected $attributes = [
        'name' => 'rebingNative',
    ];

    public function type(): Type
    {
        return Type::string();
    }

    public function resolve($root, array $args): string
    {
        return 'native';
    }
}
