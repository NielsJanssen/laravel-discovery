<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Traits;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(name: 'TraitType')]
#[Input(name: 'TraitInput')]
trait LooksLikeAType {}
