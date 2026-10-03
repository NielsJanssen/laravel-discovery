<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Omitted;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Omitted;

#[Input]
final readonly class RetagBook
{
    /**
     * @param  list<Note>|Omitted  $notes
     */
    public function __construct(
        public Tone|Omitted $tone = Omitted::Value,
        public Note|Omitted|null $note = Omitted::Value,
        #[Field(of: Note::class)]
        public array|Omitted $notes = Omitted::Value,
        #[Field(rules: ['required', 'integer', 'min:1'])]
        public int|Omitted $pages = Omitted::Value,
    ) {}
}
