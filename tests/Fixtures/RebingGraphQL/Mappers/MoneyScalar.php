<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Contracts\TypeConvertible;

final class MoneyScalar extends ScalarType implements TypeConvertible
{
    public string $name = 'Money';

    public ?string $description = 'An amount and a currency, as "12.50 EUR"';

    public function serialize($value): string
    {
        return $value instanceof Money ? $value->format() : throw new Error('Money expects a Money instance.');
    }

    public function parseValue($value): Money
    {
        return is_string($value) ? Money::parse($value) : throw new Error('Money expects a string.');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): Money
    {
        return $valueNode instanceof StringValueNode ? Money::parse($valueNode->value) : throw new Error('Money expects a string.');
    }

    public function toType(): Type
    {
        return new self();
    }
}
