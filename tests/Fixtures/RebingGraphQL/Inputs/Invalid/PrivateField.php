<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class PrivateField
{
    #[Field]
    private string $title = '';

    public function title(): string
    {
        return $this->title;
    }
}
