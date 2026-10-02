<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Workbench\App\Models\User;

#[Type]
final class MethodFieldWithModel
{
    #[Field]
    public function ownerName(User $owner): string
    {
        return $owner->name;
    }
}
