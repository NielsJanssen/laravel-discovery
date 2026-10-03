<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Naming\FieldCase;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type(naming: FieldCase::Preserve)]
final class PlainVolume
{
    public string $pageCount = '1';

    #[Field]
    public function coverUrl(int $maxWidth = 100): string
    {
        return "cover-$maxWidth";
    }

    #[Query]
    public function findPlainVolume(string $searchTerm): self
    {
        return new self();
    }
}
