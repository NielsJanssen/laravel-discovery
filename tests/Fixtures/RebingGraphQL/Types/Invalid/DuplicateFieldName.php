<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class DuplicateFieldName
{
    public string $label = 'label';

    #[Field(name: 'label')]
    public function computedLabel(): string
    {
        return 'computed';
    }
}
