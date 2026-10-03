<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\FieldDecorator;

/** A field attribute that resolves the field through a BatchLoader; `use ResolvesThroughBatchLoader` supplies decorate(). */
interface BatchedFieldDecorator extends FieldDecorator
{
    /**
     * @return class-string<BatchLoader>
     */
    public function loader(): string;

    /**
     * @param  string  $fieldName  the PHP property or method name
     * @return array<string, mixed>
     */
    public function options(string $fieldName): array;
}
