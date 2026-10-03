<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

final class AcmeMeta
{
    /**
     * @return array<string, array<string, string>> the field types of each resource, keyed by field name
     */
    public function resources(): array
    {
        return [
            'AcmeWarehouse' => ['name' => 'string', 'capacity' => 'int'],
            'AcmeDepot' => ['code' => 'string'],
        ];
    }
}
