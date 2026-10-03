<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(replace: true)]
class AcmeUserWithBilling extends AcmeUser
{
    public function __construct(
        string $name = 'Ada',
        public ?string $billingReference = 'B-1',
    ) {
        parent::__construct($name);
    }
}
