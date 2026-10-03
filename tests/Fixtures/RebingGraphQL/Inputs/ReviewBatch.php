<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final readonly class ReviewBatch
{
    /**
     * @param  list<Review>  $reviews
     */
    public function __construct(
        #[Field(of: Review::class)]
        public array $reviews,
    ) {}
}
