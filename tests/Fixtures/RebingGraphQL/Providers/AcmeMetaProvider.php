<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Providers;

use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Position;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeDefinition;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeProvider;

final readonly class AcmeMetaProvider implements TypeProvider
{
    public function __construct(private AcmeMeta $meta) {}

    public function types(): iterable
    {
        foreach ($this->meta->resources() as $name => $fields) {
            yield new TypeDefinition(
                name: $name,
                kind: Position::Output,
                fields: static function (TypeContext $context) use ($fields): iterable {
                    foreach ($fields as $field => $type) {
                        yield new Field(name: $field, type: $type, description: "The {$context->name} $field");
                    }
                },
                description: "A provided $name",
            );
        }
    }
}
