<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;
use Closure;

/** Configures a property or method of a #[Type] or #[Input] class as a GraphQL field. */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
final readonly class Field
{
    /**
     * @param  array<int|string, mixed>|Closure|null  $rules  validation rules, only for a field of an #[Input]
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
    ) {}

    public function hasRules(): bool
    {
        return $this->rules !== null && $this->rules !== [];
    }
}
