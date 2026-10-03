<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

/** Contributes fields to a #[Type] class when its GraphQL type is built. */
interface TypeFactory
{
    /**
     * @return iterable<Field>
     */
    public function fields(TypeContext $context): iterable;
}
