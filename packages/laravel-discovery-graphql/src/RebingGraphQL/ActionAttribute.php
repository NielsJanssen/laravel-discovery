<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** The arguments #[Query] and #[Mutation] share. */
abstract class ActionAttribute implements Action
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $schema = null,
        public ?string $description = null,
        public bool $list = false {
            get => $this->list || $this->of !== null;
        },
        public bool $nullable = false,
        public ?string $of = null,
        public bool $nullableItems = false,
    ) {}
}
