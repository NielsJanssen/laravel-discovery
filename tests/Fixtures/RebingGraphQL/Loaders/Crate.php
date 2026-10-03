<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Loaders;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading\Load;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class Crate
{
    #[Load(NullLoader::class)]
    public string $label = '';

    #[Load(NullLoader::class)]
    public ?string $note = null;

    public function __construct(public string $name) {}

    #[Field(of: 'String', nullable: true), Load(WrongLengthLoader::class)]
    public function labels(): array
    {
        return [];
    }
}
