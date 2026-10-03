<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Eloquent\Invalid;

use Illuminate\Database\Eloquent\Model;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
class AuthorizedTraitProperty extends Model
{
    use AuthorizesMedia;
}
