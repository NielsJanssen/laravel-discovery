<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Min;

#[Input]
final readonly class ShelveVolume
{
    public function __construct(
        #[Min(3, message: 'A shelf label needs three letters.')]
        public string $shelfLabel,
        #[Field(rules: ['nullable', 'integer', 'min:1'])]
        public ?int $rowNumber = null,
        #[Field(name: 'binNo')]
        public ?string $binNumber = null,
        public ?ShelfSpot $exactSpot = null,
        #[Field(rules: static function (array $values): array {
            return ($values['shelfLabel'] ?? null) === 'Vault' ? ['required'] : ['nullable'];
        })]
        public ?string $vaultCode = null,
    ) {}
}
