<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;

#[Input]
final class MethodField
{
    public string $title = '';

    #[Field]
    public function shout(): string
    {
        return strtoupper($this->title);
    }
}
