<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Inputs\Invalid;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Input;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Type;

#[Type, Input]
final class RulesOnMethod
{
    public string $title = '';

    #[Field(rules: ['min:2'])]
    public function shout(): string
    {
        return strtoupper($this->title);
    }
}
