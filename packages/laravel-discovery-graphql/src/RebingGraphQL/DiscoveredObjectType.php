<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Foundation\Application;
use Rebing\GraphQL\Support\Type as RebingType;

/** A Rebing object type built from a DiscoveredType. */
final class DiscoveredObjectType extends RebingType
{
    public function __construct(
        private readonly Application $app,
        private readonly DiscoveredType $discoveredType,
    ) {}

    public function attributes(): array
    {
        return $this->discoveredType->attributes();
    }

    public function fields(): array
    {
        return $this->app->make(ObjectFields::class)->build($this->discoveredType, $this->discoveredType->class);
    }
}
