<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Arg
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        /** @var array<int|string, mixed>|\Closure|null $rules */
        public array|\Closure|null $rules = null,
        public readonly ?string $description = null,
        public readonly ?string $deprecationReason = null,
        /** The type of the items of a list argument, as `type:` names the type of a single value. */
        public readonly ?string $of = null,
        public readonly bool $nullableItems = false,
    ) {}

    public function hasRules(): bool
    {
        return $this->rules !== null && $this->rules !== [];
    }
}
