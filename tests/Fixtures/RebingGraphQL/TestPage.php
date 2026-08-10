<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\ComposedFromArgs;
use NielsJanssen\Laravel\Validation\Rule\Min;

/** A value object that hydrates itself and carries its own validation attributes. */
final readonly class TestPage implements ComposedFromArgs
{
    public function __construct(
        #[Min(1)]
        public int $offset = 1,
    ) {}

    public static function fromArgs(array $args): static
    {
        return new static(offset: $args['offset'] ?? 1);
    }
}
