<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use Illuminate\Database\Eloquent\Model;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class InputModel extends Model {}
