<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final readonly class AssignBatch
{
    /**
     * @param  list<AssignBook>  $items
     */
    public function __construct(
        #[Field(of: AssignBook::class)]
        public array $items = [],
        public ?AssignBook $single = null,
    ) {}
}
