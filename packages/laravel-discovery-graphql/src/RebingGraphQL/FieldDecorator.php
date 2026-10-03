<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** An attribute on a #[Type] field that adjusts the field when its definition is built. */
interface FieldDecorator
{
    public function decorate(FieldBlueprint $field): void;
}
