<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;

/** One FieldBlueprint::wrapResolver() layer around the resolver it wraps. */
final readonly class WrappedResolver
{
    /**
     * @param  Closure(mixed, array<string, mixed>, mixed, ?ResolveInfo, Closure): mixed  $wrapper
     */
    public function __construct(
        private Closure $wrapper,
        private Closure $next,
    ) {}

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $root, array $args, mixed $context, ?ResolveInfo $info = null): mixed
    {
        return ($this->wrapper)($root, $args, $context, $info, $this->next);
    }
}
