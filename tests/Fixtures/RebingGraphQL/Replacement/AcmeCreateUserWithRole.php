<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input(replace: true)]
class AcmeCreateUserWithRole extends AcmeCreateUser
{
    public function __construct(
        string $name,
        #[Field(rules: ['max:10'])]
        public string $role = 'member',
    ) {
        parent::__construct($name);
    }
}
