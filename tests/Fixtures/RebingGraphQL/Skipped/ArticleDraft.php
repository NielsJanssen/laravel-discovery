<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Skipped;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Tests\Fixtures\RebingGraphQL\ThirdParty\Acme\HasAudit;
use Tests\Fixtures\RebingGraphQL\ThirdParty\Acme\Resource;

#[Input]
final class ArticleDraft extends Resource
{
    use HasAudit;
    use QueuesDrafts;

    public string $title = '';
}
