<?php

declare(strict_types=1);

namespace NielsJanssen\Laravel\Discovery\RebingGraphQL;

use Illuminate\Database\Eloquent\Model;

readonly class DiscoveredModelBinding
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        public string $paramName,
        public string $argName,
        public string $modelClass,
        public bool $nullable,
        /** Explicit GraphQL type from #[Arg(type:)]; null defaults to the ID scalar. */
        public ?string $type = null,
        public bool $hasUserRules = false,
    ) {}
}
