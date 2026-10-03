<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Rebing\GraphQL\Support\InputType;

#[Input]
final class RebingInput extends InputType {}
