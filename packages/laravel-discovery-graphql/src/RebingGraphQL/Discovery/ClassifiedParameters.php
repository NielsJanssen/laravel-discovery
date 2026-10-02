<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL\Discovery;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Argument\ComposedFromArgs;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredArg;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\DiscoveredModelBinding;

/** The parameters of one method, grouped by how each is filled at resolve time. */
final readonly class ClassifiedParameters
{
    /**
     * @param  list<DiscoveredArg>  $args
     * @param  array<string, 'root'|'context'|'info'>  $injections
     * @param  array<string, class-string>  $containerInjections
     * @param  array<string, class-string<ComposedFromArgs>>  $argCompositions
     * @param  list<DiscoveredModelBinding>  $modelBindings
     */
    public function __construct(
        public array $args = [],
        public array $injections = [],
        public array $containerInjections = [],
        public array $argCompositions = [],
        public array $modelBindings = [],
    ) {}
}
