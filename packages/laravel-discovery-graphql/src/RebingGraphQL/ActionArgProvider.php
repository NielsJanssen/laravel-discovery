<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

interface ActionArgProvider
{
    /**
     * GraphQL arg definitions to merge into the field's args(), in Rebing's array shape:
     *   ['argName' => ['type' => GraphQLType, 'defaultValue' => ..., 'rules' => [...]], ...]
     *
     * Called at field-init time (after cache rehydration), so GraphQLType instances are
     * safe to construct here.
     */
    public function provideArgs(): array;

    /** @return list<class-string<ComposedFromArgs>> */
    public function provideValueObjects(): array;
}
