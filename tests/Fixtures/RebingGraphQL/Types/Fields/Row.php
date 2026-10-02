<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Fields;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Row
{
    public string $label {
        get => "row {$this->id}";
    }

    public function __construct(
        public int $id,
        public string $name = 'name',
        public ?string $note = null,
        public float $score = 1.5,
        public bool $active = true,
    ) {}
}
