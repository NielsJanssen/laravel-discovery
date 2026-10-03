<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldBlueprint;

/**
 * The decorate() of a BatchedFieldDecorator: the field's value comes from its loader.
 *
 * @phpstan-require-implements BatchedFieldDecorator
 */
trait ResolvesThroughBatchLoader
{
    public function decorate(FieldBlueprint $field): void
    {
        $field->resolveWith(new BatchedResolver($field, $this->loader(), $this->options($field->field->phpName))(...));
    }
}
