<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(replace: true)]
#[Input]
class AcmeAccountOutputOnly extends AcmeAccount
{
    public function __construct(
        string $label = 'Main',
        public string $plan = 'free',
    ) {
        parent::__construct($label);
    }
}
