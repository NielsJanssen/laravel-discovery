<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Authorize;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Validation\Rule\Max;
use NielsJanssen\Laravel\Validation\Rule\Min;
use Workbench\App\Models\User;

#[Input]
final readonly class CreateBook
{
    /**
     * @param  list<Chapter>  $chapters
     */
    public function __construct(
        #[Min(2), Max(255)]
        public string $title,
        public Genre $genre,
        #[Authorize('attach')]
        public User $publisher,
        public ?Address $shipTo = null,
        #[Field(of: Chapter::class)]
        public array $chapters = [],
        #[Field(rules: ['nullable', 'date'])]
        public ?string $publishAt = null,
    ) {}
}
