<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Enums;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Diary
{
    public function __construct(
        public Mood $mood,
        public ?Genre $genre = null,
    ) {}

    #[Field]
    public function feels(Mood $mood): bool
    {
        return $this->mood === $mood;
    }

    #[Field]
    public function feelsLike(Mood $mood = Mood::Calm): string
    {
        return $mood->name;
    }
}
