<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Mappers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Contracts\TypeConvertible;

final class DateTimeScalar extends ScalarType implements TypeConvertible
{
    public string $name = 'DateTime';

    public function serialize($value): string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : throw new Error('DateTime expects a Carbon instance.');
    }

    public function parseValue($value): CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value) : throw new Error('DateTime expects a string.');
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): CarbonImmutable
    {
        return $valueNode instanceof StringValueNode ? CarbonImmutable::parse($valueNode->value) : throw new Error('DateTime expects a string.');
    }

    public function toType(): Type
    {
        return new self();
    }
}
