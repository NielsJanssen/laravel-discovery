<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Skipped;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;
use Tests\Fixtures\RebingGraphQL\ThirdParty\Acme\HasAudit;
use Tests\Fixtures\RebingGraphQL\ThirdParty\Acme\Resource;

#[Type]
final class Article extends Resource
{
    use HasAudit;

    public string $vendorId = 'redeclared';

    public string $title = 'Dune';
}
