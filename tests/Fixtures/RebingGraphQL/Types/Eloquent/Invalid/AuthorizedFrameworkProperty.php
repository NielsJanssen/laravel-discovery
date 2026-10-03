<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent\Invalid;

use Illuminate\Database\Eloquent\Model;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class AuthorizedFrameworkProperty extends Model
{
    #[Authorize]
    public $timestamps = false;
}
