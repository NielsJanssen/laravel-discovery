<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\AsArgs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Workbench\App\Models\User;

#[Input]
final readonly class Destination
{
    public function __construct(
        public string $street,
        #[Field(rules: ['min:3'])]
        public string $city,
        #[Authorize('deliver', message: 'Not a courier')]
        public ?User $courier = null,
    ) {}
}
