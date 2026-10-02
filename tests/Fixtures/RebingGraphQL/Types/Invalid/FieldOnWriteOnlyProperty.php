<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Types\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type]
final class FieldOnWriteOnlyProperty
{
    public string $stored = '';

    #[Field]
    public string $label {
        set(string $value) {
            $this->stored = $value;
        }
    }
}
