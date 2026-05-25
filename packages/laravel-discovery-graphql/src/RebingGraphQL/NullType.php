<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Contracts\TypeConvertible;

class NullType extends ScalarType implements TypeConvertible
{
    public string $name = 'Null';

    public ?string $description = 'Represents the absence of a return value.';

    public function __construct()
    {
        parent::__construct();
    }

    public function serialize(mixed $value): null
    {
        return null;
    }

    public function parseValue(mixed $value): never
    {
        throw new \RuntimeException('The Null type cannot be used as an input argument.');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): never
    {
        throw new \RuntimeException('The Null type cannot be used as an input argument.');
    }

    public function toType(): Type
    {
        return new static();
    }
}
