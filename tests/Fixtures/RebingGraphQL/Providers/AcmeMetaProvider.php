<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;
use Tests\Fixtures\RebingGraphQL\ContainerService;

final readonly class AcmeMetaProvider implements TypeProvider
{
    /** The field types of each resource, keyed by field name. */
    private const array RESOURCES = [
        'AcmeWarehouse' => ['name' => 'string', 'capacity' => 'int'],
        'AcmeDepot' => ['code' => 'string'],
    ];

    public function __construct(private ContainerService $service) {}

    public function types(): iterable
    {
        foreach (self::RESOURCES as $name => $fields) {
            yield new TypeDefinition(
                name: $name,
                kind: Position::Output,
                fields: static function (TypeContext $context) use ($fields): iterable {
                    foreach ($fields as $field => $type) {
                        yield new Field(name: $field, type: $type, description: "The {$context->name} $field");
                    }
                },
                description: "A provided $name{$this->service->suffix()}",
            );
        }
    }
}
