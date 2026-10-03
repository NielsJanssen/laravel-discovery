<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Validation\Rule\Min;

#[Input(naming: FieldCase::Camel)]
final readonly class TrackParcel
{
    public function __construct(
        #[Min(5, message: 'A tracking code has five characters.')]
        public string $trackingCode,
        #[Field(name: 'carrierName')]
        public ?string $carrier = null,
        #[Field(rules: static function (array $values): array {
            return ($values['trackingCode'] ?? '') === 'PRIORITY' ? ['required', 'min:3'] : ['nullable'];
        })]
        public ?string $deliveryNote = null,
    ) {}
}
