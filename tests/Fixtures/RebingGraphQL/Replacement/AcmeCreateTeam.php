<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Replacement;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class AcmeCreateTeam
{
    /**
     * @param  list<AcmeCreateUser>  $members
     */
    public function __construct(
        public AcmeCreateUser $owner,
        #[Field(of: AcmeCreateUser::class)]
        public array $members = [],
    ) {}
}
