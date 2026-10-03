<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ComposedFromArgs;

interface ActionArgProvider
{
    /**
     * GraphQL arg definitions to merge into the field's args(), in Rebing's array shape:
     *   ['argName' => ['type' => GraphQLType, 'defaultValue' => ..., 'rules' => [...]], ...]
     *
     * Discovery calls it too, to check arg names, so it must not resolve discovered types such as `GraphQL::type(...)`.
     *
     * @return array<string, array<string, mixed>>
     */
    public function provideArgs(): array;

    /** @return list<class-string<ComposedFromArgs>> */
    public function provideValueObjects(): array;
}
