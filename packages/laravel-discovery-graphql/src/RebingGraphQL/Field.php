<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use Closure;
use GraphQL\Type\Definition\ResolveInfo;

/** Configures a member of a #[Type] or #[Input] class as a GraphQL field, or describes a field a TypeFactory yields. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Field
{
    /**
     * @param  array<int|string, mixed>|Closure|null  $rules  validation rules, only for a field of an #[Input]
     * @param  Closure(mixed, array<string, mixed>, mixed, ResolveInfo): mixed|null  $resolve  how a field yielded by a TypeFactory gets its value
     * @param  array<string, Field>  $args  the args of a field yielded by a TypeFactory, keyed by arg name
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $of = null,
        public bool $nullable = false,
        public bool $nullableItems = false,
        public ?string $description = null,
        public ?string $deprecationReason = null,
        public array|Closure|null $rules = null,
        public ?Closure $resolve = null,
        public array $args = [],
    ) {}

    /** Whether the field sets what only a TypeFactory field can: resolve or args. */
    public function isFactoryOnly(): bool
    {
        return $this->resolve !== null || $this->args !== [];
    }

    public function hasRules(): bool
    {
        return $this->rules !== null && $this->rules !== [];
    }
}
