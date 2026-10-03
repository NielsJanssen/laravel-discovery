<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Extensions;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Root;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeExtension;
use Tests\Fixtures\RebingGraphQL\Naming\SnakeNote;

#[TypeExtension(SnakeNote::class)]
final class AcmeSnakeNoteExtras
{
    #[Field]
    public function readingTime(#[Root] SnakeNote $note, int $wordsPerMinute = 1): int
    {
        return intdiv(str_word_count($note->noteText), $wordsPerMinute);
    }
}
