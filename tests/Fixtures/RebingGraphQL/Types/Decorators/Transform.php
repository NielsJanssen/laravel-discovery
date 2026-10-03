<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Decorators;

use Attribute;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldBlueprint;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDecorator;

/** Applies a closure to the resolved value; the closure keeps it out of serialize(). */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Transform implements FieldDecorator
{
    public function __construct(
        public Closure $transform,
    ) {}

    public function decorate(FieldBlueprint $field): void
    {
        $transform = $this->transform;

        $field->wrapResolver(static fn(mixed $root, array $args, mixed $context, ?ResolveInfo $info, Closure $next): mixed => $transform($next($root, $args, $context, $info)));
    }
}
