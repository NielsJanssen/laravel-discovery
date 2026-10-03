<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use Workbench\App\Models\User;

#[Input]
final readonly class Review
{
    public function __construct(
        #[Field(rules: static function (array $values): array {
            return ($values['stars'] ?? 0) > 3 ? ['min:10'] : ['min:1'];
        })]
        public string $body,
        public int $stars,
        #[Authorize('review', message: 'Not a reviewer')]
        public ?User $reviewer = null,
    ) {}
}
