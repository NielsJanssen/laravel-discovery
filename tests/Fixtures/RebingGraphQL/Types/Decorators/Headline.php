<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Decorators;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Headline
{
    public string $plain = 'quiet news';

    #[Uppercase]
    public string $shouted = 'loud news';

    #[Uppercase]
    public string $empty = '';

    #[Transform(static function (mixed $value): string {
        return is_string($value) ? strrev($value) : '';
    })]
    #[Transform(static function (mixed $value): string {
        return is_string($value) ? "[$value]" : '';
    })]
    public string $reversed = 'abc';

    #[Field]
    #[Uppercase]
    public function teaser(string $prefix = ''): string
    {
        return $prefix . 'read more';
    }
}
