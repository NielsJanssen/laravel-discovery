<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Mutation;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Query;

final class ReviewMutation
{
    #[Mutation]
    public function review(Review $review): string
    {
        return $review->reviewer->name ?? 'anonymous';
    }

    #[Mutation]
    public function reviewAll(ReviewBatch $batch): int
    {
        return count($batch->reviews);
    }

    #[Query]
    public function ping(): string
    {
        return 'pong';
    }
}
