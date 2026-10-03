<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Loading;

/** Loads a field's value for many parent objects at once. */
interface BatchLoader
{
    /**
     * @param  list<object>  $roots  the parent objects, each once
     * @param  array<string, mixed>  $options  from the field's BatchedFieldDecorator attribute
     * @param  array<string, mixed>  $args  the field's GraphQL args
     * @return list<mixed> one result per root, in order
     */
    public function load(array $roots, array $options, array $args): array;
}
