<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Naming;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Arg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Volume
{
    public function __construct(
        public string $title = 'Dune',
        public int $pageCount = 412,
        #[Field(name: 'ISBN')]
        public string $isbnCode = '978-0441013593',
        public Placement $shelfPlacement = Placement::TopRow,
    ) {}

    #[Field]
    public function coverUrl(int $maxWidth = 100, #[Arg(name: 'fileFormat')] string $imageFormat = 'png'): string
    {
        return "cover-$maxWidth.$imageFormat";
    }
}
