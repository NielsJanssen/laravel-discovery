<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use Workbench\App\Models\User;

final class BindingCollision
{
    #[Query]
    public function ownerName(User $volumeOwner, string $volume_owner): string
    {
        return $volumeOwner->name . $volume_owner;
    }
}
