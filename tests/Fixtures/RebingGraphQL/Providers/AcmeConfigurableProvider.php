<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;

final readonly class AcmeConfigurableProvider implements TypeProvider
{
    /**
     * @param  list<mixed>  $definitions
     */
    public function __construct(private array $definitions = []) {}

    /**
     * @return list<TypeDefinition>
     */
    public function types(): iterable
    {
        return $this->definitions;
    }
}
