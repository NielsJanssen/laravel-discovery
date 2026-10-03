<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Min;
use Workbench\App\Models\User;

#[Input]
final readonly class PlaceOrder
{
    public function __construct(
        #[Field(name: 'heading', description: 'Printed on the slip')]
        #[Min(2, message: 'A heading needs two letters.')]
        public string $title,
        public Shelf $shelf,
        #[Authorize('stock')]
        public User $supplier,
        public User $buyer,
        public ?Destination $shipTo = null,
        #[Field(rules: static function (array $values): array {
            return ($values['shelf'] ?? null) === Shelf::Poetry ? ['integer', 'max:3'] : ['integer', 'max:100'];
        })]
        public int $copies = 1,
        #[Field(name: 'note', deprecationReason: 'Use shipTo')]
        public ?string $remark = null,
        public string $wrapping = 'plain',
    ) {}
}
