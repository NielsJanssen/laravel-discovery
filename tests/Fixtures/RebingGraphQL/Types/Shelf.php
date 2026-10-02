<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types;

use ArrayAccess;
use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use Tests\Fixtures\RebingGraphQL\ContainerService;

/**
 * @implements ArrayAccess<string, string>
 */
final class Shelf implements ArrayAccess
{
    public function __construct(
        public string $label = 'Fiction',
    ) {}

    public function summary(#[Arg(description: 'How often to repeat the label')] int $times, ContainerService $service, ResolveInfo $info): string
    {
        return "{$info->fieldName}: " . str_repeat($this->label, $times) . $service->suffix();
    }

    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    public function offsetGet(mixed $offset): string
    {
        return 'read through offsetGet';
    }

    public function offsetSet(mixed $offset, mixed $value): void {}

    public function offsetUnset(mixed $offset): void {}
}
