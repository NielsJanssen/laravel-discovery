<?php

declare(strict_types=1);

namespace Tests\Fixtures\RebingGraphQL\Factories;

use GraphQL\Type\Definition\ResolveInfo;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\Field;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeContext;
use NielsJanssen\Laravel\Discovery\RebingGraphQL\TypeFactory;
use Tests\Fixtures\RebingGraphQL\ContainerService;

final readonly class AcmeLookupFields implements TypeFactory
{
    public function __construct(private ContainerService $service) {}

    public function fields(TypeContext $context): iterable
    {
        yield new Field(
            name: 'regionLabel',
            type: 'string',
            description: 'Looks a region up',
            args: [
                'regionCode' => new Field(type: 'string', description: 'The code'),
                'upper' => new Field(type: 'bool', nullable: true),
            ],
            resolve: function (mixed $root, array $args, mixed $context, ResolveInfo $info): string {
                $label = "Region {$args['regionCode']}{$this->service->suffix()}";

                return ($args['upper'] ?? false) ? strtoupper($label) : $label;
            },
        );
    }
}
